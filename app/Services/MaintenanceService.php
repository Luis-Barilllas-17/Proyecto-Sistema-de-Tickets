<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\ImpactLevel;
use App\Models\MaintenanceEvidence;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlanEquipment;
use App\Models\MaintenanceSequence;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use App\Models\User;
use App\Support\UploadSecurityPolicy;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MaintenanceService
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function __construct(
        private TicketWorkflowService $workflow,
        private TicketHistoryService $history,
    ) {}

    public function generateDueOrders(?User $actor = null, ?Carbon $asOf = null): Collection
    {
        $asOf ??= today();
        $actor = $this->resolveActor($actor);

        return MaintenancePlanEquipment::with(['plan.checklistItems', 'equipment'])
            ->where('is_active', true)
            ->whereHas('plan', fn ($query) => $query->where('is_active', true))
            ->whereHas('equipment', fn ($query) => $query->where('is_active', true)->where('status', '!=', 'retired'))
            ->orderBy('next_due_date')
            ->get()
            ->filter(fn (MaintenancePlanEquipment $assignment) => $assignment->next_due_date
                ->lte($asOf->copy()->addDays($assignment->plan->lead_days)))
            ->map(fn (MaintenancePlanEquipment $assignment) => $this->generateForAssignment($assignment, $actor, 'automatic'))
            ->filter()
            ->values();
    }

    public function generateForAssignment(
        MaintenancePlanEquipment $assignment,
        User $actor,
        string $source = 'manual',
    ): MaintenanceOrder {
        return DB::transaction(function () use ($assignment, $actor, $source): MaintenanceOrder {
            $assignment = MaintenancePlanEquipment::with(['plan.checklistItems', 'equipment'])
                ->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $plan = $assignment->plan;
            $equipment = $assignment->equipment;

            if (! $assignment->is_active || ! $plan->is_active || ! $equipment->is_active || $equipment->status === 'retired') {
                throw ValidationException::withMessages(['schedule' => 'La programación o el equipo ya no está activo.']);
            }

            $existing = MaintenanceOrder::where('maintenance_plan_id', $plan->id)
                ->where('equipment_id', $equipment->id)
                ->whereDate('scheduled_for', $assignment->next_due_date)
                ->first();
            if ($existing) {
                return $existing;
            }

            $ticket = $this->createTicket($assignment, $actor);
            $start = $plan->default_start_time
                ? Carbon::parse($assignment->next_due_date->format('Y-m-d').' '.$plan->default_start_time)
                : null;

            $order = MaintenanceOrder::create([
                'number' => $this->nextNumber(),
                'maintenance_plan_id' => $plan->id,
                'maintenance_plan_equipment_id' => $assignment->id,
                'equipment_id' => $equipment->id,
                'ticket_id' => $ticket->id,
                'scheduled_for' => $assignment->next_due_date,
                'scheduled_start_at' => $start,
                'scheduled_end_at' => $start?->copy()->addMinutes($plan->duration_minutes),
                'status' => 'work_order_generated',
                'assigned_to_id' => $plan->assigned_user_id,
                'generation_source' => $source,
                'generated_at' => now(),
                'created_by_id' => $actor->id,
            ]);

            foreach ($plan->checklistItems as $item) {
                $order->items()->create([
                    'maintenance_checklist_item_id' => $item->id,
                    'label' => $item->label,
                    'sort_order' => $item->sort_order,
                    'is_required' => $item->is_required,
                ]);
            }

            $assignment->update([
                'next_due_date' => $assignment->next_due_date->copy()->addMonthsNoOverflow($plan->frequency_months),
            ]);

            return $order->fresh(['plan', 'equipment', 'ticket', 'items']);
        });
    }

    public function start(MaintenanceOrder $order, User $actor): void
    {
        DB::transaction(function () use ($order, $actor): void {
            $order = MaintenanceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! in_array($order->status, ['scheduled', 'work_order_generated', 'overdue'], true)) {
                throw ValidationException::withMessages(['order' => 'La orden no está disponible para iniciar.']);
            }
            $equipment = Equipment::whereKey($order->equipment_id)->lockForUpdate()->firstOrFail();
            if ($equipment->status === 'retired') {
                throw ValidationException::withMessages(['order' => 'No se puede iniciar mantenimiento sobre un equipo dado de baja.']);
            }
            if ($equipment->status === 'maintenance') {
                throw ValidationException::withMessages(['order' => 'El equipo ya tiene un mantenimiento en ejecución.']);
            }

            $order->update([
                'status' => 'in_progress',
                'assigned_to_id' => $order->assigned_to_id ?: $actor->id,
                'started_at' => now(),
                'equipment_previous_status' => $equipment->status,
            ]);
            $equipment->update(['status' => 'maintenance', 'updated_by_id' => $actor->id]);
            $equipment->events()->create([
                'ticket_id' => $order->ticket_id,
                'event_type' => 'maintenance',
                'title' => 'Mantenimiento preventivo iniciado',
                'description' => 'Inicio de la orden '.$order->number.'.',
                'performed_at' => now(),
                'metadata' => ['maintenance_order_id' => $order->id],
                'created_by_id' => $actor->id,
            ]);
            if ($order->ticket) {
                $order->ticket->activities()->create([
                    'user_id' => $actor->id,
                    'activity_date' => today(),
                    'activity_type' => 'work',
                    'minutes_spent' => 0,
                    'description' => 'Inicio de mantenimiento preventivo '.$order->number.'.',
                    'progress_percentage' => 10,
                ]);
                $this->history->record($order->ticket, $actor, 'maintenance_started', 'Orden preventiva '.$order->number.' iniciada.');
            }
        });
    }

    public function complete(
        MaintenanceOrder $order,
        array $itemResponses,
        string $result,
        string $observations,
        array $files,
        ?string $evidenceDescription,
        User $actor,
    ): void {
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $order, $itemResponses, $result, $observations, $files, $evidenceDescription, $actor, &$storedPaths,
            ): void {
                $order = MaintenanceOrder::with(['items', 'evidences', 'ticket'])
                    ->whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($order->status !== 'in_progress') {
                    throw ValidationException::withMessages(['order' => 'La orden debe estar en ejecución antes de completarse.']);
                }

                foreach ($order->items as $item) {
                    $response = $itemResponses[$item->id] ?? [];
                    $status = $response['status'] ?? 'pending';
                    $observation = $this->clean($response['observation'] ?? null);
                    if (! in_array($status, ['completed', 'not_applicable'], true)) {
                        throw ValidationException::withMessages(["items.$item->id.status" => 'Completa todos los puntos obligatorios.']);
                    }
                    if ($status === 'not_applicable' && mb_strlen((string) $observation) < 5) {
                        throw ValidationException::withMessages(["items.$item->id.observation" => 'Justifica por qué el punto no aplica.']);
                    }
                    $item->update([
                        'status' => $status,
                        'observation' => $observation,
                        'completed_by_id' => $actor->id,
                        'completed_at' => now(),
                    ]);
                }

                if ($order->evidences->isEmpty() && empty($files)) {
                    throw ValidationException::withMessages(['evidences' => 'Adjunta al menos una evidencia del mantenimiento realizado.']);
                }
                foreach ($files as $file) {
                    $evidence = $this->storeEvidence($order, $file, $evidenceDescription, $actor);
                    $storedPaths[] = $evidence->path;
                }

                $equipment = Equipment::whereKey($order->equipment_id)->lockForUpdate()->firstOrFail();
                $restoredStatus = $result === 'repair_required'
                    ? 'repair'
                    : ($order->equipment_previous_status && $order->equipment_previous_status !== 'maintenance'
                        ? $order->equipment_previous_status : 'available');
                $equipment->update(['status' => $restoredStatus, 'updated_by_id' => $actor->id]);

                $order->update([
                    'status' => 'completed',
                    'result' => $result,
                    'observations' => $this->clean($observations),
                    'completed_at' => now(),
                    'completed_by_id' => $actor->id,
                ]);
                $order->assignment()->update([
                    'last_completed_on' => today(),
                ]);
                $equipment->events()->create([
                    'ticket_id' => $order->ticket_id,
                    'event_type' => 'maintenance',
                    'title' => 'Mantenimiento preventivo completado',
                    'description' => $this->clean($observations),
                    'performed_at' => now(),
                    'metadata' => [
                        'maintenance_order_id' => $order->id,
                        'result' => $result,
                        'checklist_items' => $order->items->count(),
                    ],
                    'created_by_id' => $actor->id,
                ]);

                if ($order->ticket) {
                    $ticket = $order->ticket;
                    $ticket->activities()->create([
                        'user_id' => $actor->id,
                        'activity_date' => today(),
                        'activity_type' => 'work',
                        'minutes_spent' => max(1, $order->started_at?->diffInMinutes(now()) ?? $order->plan->duration_minutes),
                        'description' => 'Mantenimiento preventivo completado: '.$this->clean($observations),
                        'progress_percentage' => 100,
                    ]);
                    $solvedStatus = TicketStatus::where('slug', 'solucionado')->where('is_active', true)->firstOrFail();
                    $ticket->update([
                        'ticket_status_id' => $solvedStatus->id,
                        'solution' => 'Mantenimiento preventivo completado. '.$this->clean($observations),
                        'solution_by_id' => $actor->id,
                        'solved_at' => now(),
                        'last_activity_at' => now(),
                        'lock_version' => $ticket->lock_version + 1,
                    ]);
                    $this->history->record($ticket, $actor, 'maintenance_completed', 'Orden preventiva '.$order->number.' completada.', [], [
                        'result' => $result,
                        'maintenance_order_id' => $order->id,
                    ]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    private function createTicket(MaintenancePlanEquipment $assignment, User $actor): Ticket
    {
        $plan = $assignment->plan;
        $equipment = $assignment->equipment;
        $category = TicketCategory::where('code', 'SOP')->where('is_active', true)->firstOrFail();
        $subcategory = TicketSubcategory::where('ticket_category_id', $category->id)
            ->where('code', 'HWD')->where('is_active', true)->firstOrFail();
        $impact = ImpactLevel::where('value', 1)->where('is_active', true)->firstOrFail();
        $urgency = UrgencyLevel::where('value', 1)->where('is_active', true)->firstOrFail();

        $ticket = Ticket::create([
            'number' => $this->workflow->nextNumber(),
            'requester_id' => $plan->assigned_user_id ?: $actor->id,
            'created_by_id' => $actor->id,
            'source' => 'it_internal',
            'is_draft' => true,
            'title' => 'Mantenimiento preventivo · '.$equipment->inventory_code,
            'description' => 'Orden preventiva del plan '.$plan->code.' para '.$equipment->name.'. Fecha programada: '.$assignment->next_due_date->format('d/m/Y').'.',
            'ticket_category_id' => $category->id,
            'ticket_subcategory_id' => $subcategory->id,
            'requester_area_id' => $equipment->area_id ?: $actor->area_id,
            'requester_department_id' => $equipment->department_id,
            'location' => $equipment->location,
            'attention_type' => 'onsite',
            'impact_level_id' => $impact->id,
            'urgency_level_id' => $urgency->id,
            'ticket_status_id' => $this->workflow->initialStatus()->id,
            'approval_status' => 'not_required',
            'last_activity_at' => now(),
        ]);
        $ticket->equipmentLinks()->create([
            'equipment_id' => $equipment->id,
            'inventory_code' => $equipment->inventory_code,
            'equipment_name' => $equipment->name,
            'linked_by_id' => $actor->id,
        ]);
        $this->workflow->submit($ticket, $actor);
        $ticket->refresh();

        if ($ticket->approval_status !== 'pending' && ($plan->assigned_it_area_id || $plan->assigned_it_group_id || $plan->assigned_user_id)
            && ($ticket->current_it_area_id !== $plan->assigned_it_area_id
                || $ticket->current_it_group_id !== $plan->assigned_it_group_id
                || $ticket->current_assignee_id !== $plan->assigned_user_id)) {
            $this->workflow->assign(
                $ticket,
                $actor,
                $plan->assigned_it_area_id,
                $plan->assigned_it_group_id,
                $plan->assigned_user_id,
                'Asignación definida por el plan preventivo '.$plan->code.'.',
                'assigned',
            );
        }

        return $ticket->fresh();
    }

    private function nextNumber(): string
    {
        $year = (int) now()->format('Y');
        MaintenanceSequence::firstOrCreate(['year' => $year], ['last_number' => 0]);
        $sequence = MaintenanceSequence::where('year', $year)->lockForUpdate()->firstOrFail();
        $sequence->increment('last_number');

        return sprintf('MP-%d-%06d', $year, $sequence->fresh()->last_number);
    }

    private function storeEvidence(
        MaintenanceOrder $order,
        UploadedFile $file,
        ?string $description,
        User $actor,
    ): MaintenanceEvidence {
        if (! $file->isValid()) {
            throw new RuntimeException('La evidencia recibida no es válida.');
        }
        UploadSecurityPolicy::assertSafeSize($file);
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new RuntimeException('El tipo de evidencia no está permitido.');
        }
        $path = $file->storeAs(
            'maintenance-evidences/'.$order->id,
            Str::uuid()->toString().'.'.$extension,
            'local',
        );
        if (! $path) {
            throw new RuntimeException('No fue posible almacenar la evidencia.');
        }
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName())));

        return $order->evidences()->create([
            'uploaded_by_id' => $actor->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => mb_substr($originalName ?: 'evidencia.'.$extension, 0, 255),
            'mime_type' => mb_substr((string) $file->getMimeType(), 0, 120),
            'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'description' => $this->clean($description),
        ]);
    }

    private function resolveActor(?User $actor): User
    {
        return $actor
            ?? User::where('is_super_admin', true)->where('status', 'active')->first()
            ?? throw ValidationException::withMessages(['actor' => 'No existe una cuenta de servicio activa para generar órdenes.']);
    }

    private function clean(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }
}

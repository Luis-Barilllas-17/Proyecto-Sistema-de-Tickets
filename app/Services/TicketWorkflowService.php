<?php

namespace App\Services;

use App\Models\ApprovalDelegation;
use App\Models\ApprovalLevel;
use App\Models\ApprovalWorkflow;
use App\Models\PriorityMatrixRule;
use App\Models\RoutingRule;
use App\Models\ServiceLevelTarget;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\TicketAssignment;
use App\Models\TicketPriority;
use App\Models\TicketSequence;
use App\Models\TicketStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketWorkflowService
{
    public function __construct(private TicketHistoryService $history) {}

    public function nextNumber(): string
    {
        $year = (int) now()->format('Y');
        TicketSequence::firstOrCreate(['year' => $year], ['last_number' => 0]);
        $sequence = TicketSequence::where('year', $year)->lockForUpdate()->firstOrFail();
        $sequence->increment('last_number');

        return sprintf('ZT-%d-%06d', $year, $sequence->fresh()->last_number);
    }

    public function initialStatus(): TicketStatus
    {
        $status = TicketStatus::where('is_active', true)->where('is_initial', true)->orderBy('sort_order')->first()
            ?? TicketStatus::where('slug', 'registrado')->where('is_active', true)->first();

        if (! $status) {
            throw ValidationException::withMessages(['ticket_status_id' => 'No existe un estado inicial activo. Configúralo en el Módulo 3.']);
        }

        return $status;
    }

    public function status(string $slug): TicketStatus
    {
        $status = TicketStatus::where('slug', $slug)->where('is_active', true)->first();
        if (! $status) {
            throw ValidationException::withMessages(['ticket_status_id' => "El estado requerido «$slug» no está activo."]);
        }

        return $status;
    }

    public function calculatePriority(int $impactId, int $urgencyId): TicketPriority
    {
        $rule = PriorityMatrixRule::with('priority')->where('impact_level_id', $impactId)
            ->where('urgency_level_id', $urgencyId)->first();

        if (! $rule?->priority?->is_active) {
            throw ValidationException::withMessages(['impact_level_id' => 'La combinación de impacto y urgencia no tiene una prioridad activa configurada.']);
        }

        return $rule->priority;
    }

    public function submit(Ticket $ticket, User $actor, ?Request $request = null): void
    {
        DB::transaction(function () use ($ticket, $actor, $request): void {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $priority = $this->calculatePriority($ticket->impact_level_id, $ticket->urgency_level_id);
            $ticket->approvals()->delete();
            $ticket->forceFill([
                'ticket_priority_id' => $priority->id, 'is_draft' => false,
                'submitted_at' => $ticket->submitted_at ?? now(), 'last_activity_at' => now(),
                'lock_version' => $ticket->lock_version + 1,
            ])->save();
            $this->applyServiceLevel($ticket, $priority);

            $workflow = $this->matchingApprovalWorkflow($ticket, $priority);
            if ($workflow && $workflow->levels->where('is_active', true)->isNotEmpty()) {
                $route = $this->matchingRoutingRule($ticket);
                $levels = $workflow->levels->where('is_active', true)->sortBy('level_number')->values();
                foreach ($levels as $index => $level) {
                    $ticket->approvals()->create([
                        'approval_workflow_id' => $workflow->id, 'approval_level_id' => $level->id,
                        'level_number' => $level->level_number, 'level_name' => $level->name,
                        'approver_type' => $level->approver_type,
                        'approver_user_id' => $this->resolveApproverUser($ticket, $level, $route),
                        'approver_role_id' => $level->approver_role_id, 'status' => $index === 0 ? 'pending' : 'waiting',
                    ]);
                }
                $ticket->update([
                    'approval_status' => 'pending', 'ticket_status_id' => $this->status('pendiente-aprobacion')->id,
                    'current_it_area_id' => null, 'current_it_group_id' => null,
                    'current_assignee_id' => null, 'assignment_status' => null,
                ]);
                $this->history->record($ticket, $actor, 'ticket_submitted', 'Ticket enviado y remitido a aprobación.', [], [
                    'priority' => $priority->name, 'workflow' => $workflow->name,
                ], $request);
            } else {
                $ticket->update(['approval_status' => 'not_required']);
                $this->assignAutomatically($ticket, $actor, $request);
                $this->history->record($ticket, $actor, 'ticket_submitted', 'Ticket enviado para atención.', [], [
                    'priority' => $priority->name,
                ], $request);
            }
        });
    }

    public function decideApproval(Ticket $ticket, TicketApproval $approval, User $actor, string $decision, ?string $comment, ?Request $request = null): void
    {
        DB::transaction(function () use ($ticket, $approval, $actor, $decision, $comment, $request): void {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $approval = TicketApproval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($approval->ticket_id !== $ticket->id || $approval->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Este nivel ya no está disponible para decisión.']);
            }

            $delegatedFor = null;
            if ($approval->approver_user_id && $approval->approver_user_id !== $actor->id) {
                $delegatedFor = ApprovalDelegation::where('delegator_user_id', $approval->approver_user_id)
                    ->where('delegate_user_id', $actor->id)->where('is_active', true)
                    ->where('starts_at', '<=', now())->where('ends_at', '>=', now())->value('delegator_user_id');
            }
            $approval->update([
                'status' => $decision, 'decided_by_id' => $actor->id, 'delegated_for_id' => $delegatedFor,
                'comment' => $comment, 'decided_at' => now(),
            ]);

            if ($decision === 'approved') {
                $next = $ticket->approvals()->where('status', 'waiting')->orderBy('level_number')->first();
                if ($next) {
                    $next->update(['status' => 'pending']);
                    $this->history->record($ticket, $actor, 'approval_granted', 'Nivel '.$approval->level_number.' aprobado; continúa el siguiente nivel.', [], ['comment' => $comment], $request);
                } else {
                    $ticket->update(['approval_status' => 'approved']);
                    $this->history->record($ticket, $actor, 'approval_completed', 'Flujo de aprobación completado.', [], ['comment' => $comment], $request);
                    $this->assignAutomatically($ticket, $actor, $request);
                }
            } elseif ($decision === 'rejected') {
                $ticket->approvals()->where('status', 'waiting')->update(['status' => 'skipped']);
                $ticket->update(['approval_status' => 'rejected', 'last_activity_at' => now()]);
                $this->history->record($ticket, $actor, 'approval_rejected', 'Ticket rechazado durante la aprobación.', [], ['comment' => $comment], $request);
            } else {
                $ticket->approvals()->where('status', 'waiting')->update(['status' => 'skipped']);
                $ticket->update([
                    'approval_status' => 'changes_requested', 'is_draft' => true,
                    'ticket_status_id' => $this->initialStatus()->id, 'last_activity_at' => now(),
                ]);
                $this->history->record($ticket, $actor, 'approval_changes_requested', 'Ticket devuelto al solicitante para corrección.', [], ['comment' => $comment], $request);
            }
        });
    }

    public function assign(Ticket $ticket, User $actor, ?int $areaId, ?int $groupId, ?int $assigneeId, string $reason, string $action = 'assigned', ?Request $request = null): TicketAssignment
    {
        return DB::transaction(function () use ($ticket, $actor, $areaId, $groupId, $assigneeId, $reason, $action, $request): TicketAssignment {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $from = ['area_id' => $ticket->current_it_area_id, 'group_id' => $ticket->current_it_group_id, 'user_id' => $ticket->current_assignee_id];
            $ticket->assignments()->where('status', 'pending')->update(['status' => 'superseded', 'responded_at' => now()]);
            $assignment = $ticket->assignments()->create([
                'from_area_id' => $from['area_id'], 'from_group_id' => $from['group_id'], 'from_user_id' => $from['user_id'],
                'to_area_id' => $areaId, 'to_group_id' => $groupId, 'to_user_id' => $assigneeId,
                'assigned_by_id' => $actor->id, 'action' => $action, 'status' => $assigneeId ? 'pending' : 'accepted',
                'reason' => $reason, 'responded_at' => $assigneeId ? null : now(),
            ]);
            $ticket->update([
                'current_it_area_id' => $areaId, 'current_it_group_id' => $groupId,
                'current_assignee_id' => $assigneeId, 'assignment_status' => $assigneeId ? 'pending' : 'group_assigned',
                'ticket_status_id' => $this->status('asignado')->id, 'last_activity_at' => now(),
                'lock_version' => $ticket->lock_version + 1,
            ]);
            $this->history->record($ticket, $actor, $action === 'transferred' ? 'ticket_transferred' : 'ticket_assigned',
                $action === 'transferred' ? 'Ticket transferido a un nuevo responsable.' : 'Ticket asignado para atención.',
                $from, ['area_id' => $areaId, 'group_id' => $groupId, 'user_id' => $assigneeId, 'reason' => $reason], $request);

            return $assignment;
        });
    }

    public function assignAutomatically(Ticket $ticket, User $actor, ?Request $request = null): ?TicketAssignment
    {
        $route = $this->matchingRoutingRule($ticket);
        if (! $route) {
            $ticket->update(['ticket_status_id' => $this->initialStatus()->id, 'assignment_status' => 'unassigned', 'last_activity_at' => now()]);
            $this->history->record($ticket, $actor, 'routing_pending', 'No se encontró una regla automática; el ticket queda pendiente de asignación manual.', [], [], $request);

            return null;
        }

        return $this->assign($ticket, $actor, $route->it_area_id, $route->it_group_id, $route->assignee_user_id,
            'Asignación automática mediante la regla «'.$route->name.'».', 'automatic', $request);
    }

    public function changeStatus(Ticket $ticket, TicketStatus $status, User $actor, string $reason, ?Request $request = null): void
    {
        if ($ticket->is_draft || in_array($ticket->approval_status, ['pending', 'rejected', 'changes_requested'], true)) {
            throw ValidationException::withMessages(['ticket_status_id' => 'El ticket debe completar su envío y aprobación antes de cambiar de estado.']);
        }
        if ($status->is_final || in_array($status->slug, ['registrado', 'pendiente-aprobacion', 'solucionado', 'cerrado', 'cancelado'], true)) {
            throw ValidationException::withMessages(['ticket_status_id' => 'Ese estado solo puede establecerse mediante su flujo especializado.']);
        }
        $from = $ticket->status?->name;
        $ticket->update([
            'ticket_status_id' => $status->id, 'first_response_at' => $ticket->first_response_at ?? now(),
            'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
        ]);
        $this->history->record($ticket, $actor, 'status_changed', 'Estado actualizado de «'.$from.'» a «'.$status->name.'».',
            ['status' => $from], ['status' => $status->name, 'reason' => $reason], $request);
    }

    private function matchingApprovalWorkflow(Ticket $ticket, TicketPriority $priority): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::with(['levels' => fn ($query) => $query->where('is_active', true)->orderBy('level_number'), 'minimumPriority'])
            ->where('is_active', true)->where('ticket_category_id', $ticket->ticket_category_id)
            ->where(fn ($query) => $query->whereNull('ticket_subcategory_id')->orWhere('ticket_subcategory_id', $ticket->ticket_subcategory_id))
            ->where(fn ($query) => $query->whereNull('requester_area_id')->orWhere('requester_area_id', $ticket->requester_area_id))
            ->get()->filter(fn ($workflow) => ! $workflow->minimumPriority || $priority->weight >= $workflow->minimumPriority->weight)
            ->sortByDesc(fn ($workflow) => ($workflow->ticket_subcategory_id ? 4 : 0) + ($workflow->requester_area_id ? 2 : 0) + ($workflow->minimum_priority_id ? 1 : 0))->first();
    }

    private function matchingRoutingRule(Ticket $ticket): ?RoutingRule
    {
        return RoutingRule::with('itArea')->where('is_active', true)->where('ticket_category_id', $ticket->ticket_category_id)
            ->where(fn ($query) => $query->whereNull('ticket_subcategory_id')->orWhere('ticket_subcategory_id', $ticket->ticket_subcategory_id))
            ->orderBy('precedence')->get()->sortByDesc(fn ($route) => $route->ticket_subcategory_id ? 1 : 0)->first();
    }

    private function resolveApproverUser(Ticket $ticket, ApprovalLevel $level, ?RoutingRule $route): ?int
    {
        return match ($level->approver_type) {
            'requester_area_head' => $ticket->requesterArea?->head_user_id,
            'it_area_head' => $route?->itArea?->head_user_id,
            'specific_user' => $level->approver_user_id,
            default => null,
        };
    }

    private function applyServiceLevel(Ticket $ticket, TicketPriority $priority): void
    {
        $target = ServiceLevelTarget::where('is_active', true)->where('ticket_priority_id', $priority->id)
            ->where(fn ($query) => $query->whereNull('ticket_category_id')->orWhere('ticket_category_id', $ticket->ticket_category_id))
            ->where(fn ($query) => $query->whereNull('ticket_subcategory_id')->orWhere('ticket_subcategory_id', $ticket->ticket_subcategory_id))
            ->get()->sortByDesc(fn ($item) => ($item->ticket_subcategory_id ? 2 : 0) + ($item->ticket_category_id ? 1 : 0))->first();

        if (! $target) {
            $ticket->update(['response_due_at' => null, 'escalation_due_at' => null, 'resolution_due_at' => null]);

            return;
        }

        $start = now();
        $ticket->update([
            'response_due_at' => $this->addMinutes($start, $target->response_minutes, $target->business_hours_only),
            'escalation_due_at' => $this->addMinutes($start, $target->escalation_minutes, $target->business_hours_only),
            'resolution_due_at' => $this->addMinutes($start, $target->resolution_minutes, $target->business_hours_only),
        ]);
    }

    private function addMinutes(Carbon $start, int $minutes, bool $businessHoursOnly): Carbon
    {
        if (! $businessHoursOnly) {
            return $start->copy()->addMinutes($minutes);
        }
        $cursor = $start->copy();
        while ($minutes > 0) {
            if ($cursor->isWeekend()) {
                $cursor->next(Carbon::MONDAY)->setTime(8, 0);
            } elseif ($cursor->hour < 8) {
                $cursor->setTime(8, 0);
            } elseif ($cursor->hour >= 17) {
                $cursor->addDay()->setTime(8, 0);

                continue;
            }
            $available = (int) $cursor->diffInMinutes($cursor->copy()->setTime(17, 0));
            $step = min($minutes, $available);
            $cursor->addMinutes($step);
            $minutes -= $step;
            if ($minutes > 0) {
                $cursor->addDay()->setTime(8, 0);
            }
        }

        return $cursor;
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ImpactLevel;
use App\Models\PriorityMatrixRule;
use App\Models\SecurityEvent;
use App\Models\ServiceLevelTarget;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\TicketSubcategory;
use App\Models\UrgencyLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceLevelController extends Controller
{
    public function index(Request $request): View
    {
        return view('configuration.service-levels.index', [
            'statuses' => TicketStatus::orderBy('sort_order')->get(),
            'impacts' => ImpactLevel::orderBy('value')->get(),
            'urgencies' => UrgencyLevel::orderBy('value')->get(),
            'priorities' => TicketPriority::orderByDesc('weight')->get(),
            'matrix' => PriorityMatrixRule::all()->keyBy(fn ($rule) => $rule->impact_level_id.'-'.$rule->urgency_level_id),
            'targets' => ServiceLevelTarget::with(['priority', 'category', 'subcategory'])->orderBy('response_minutes')->get(),
            'categories' => TicketCategory::with(['subcategories' => fn ($query) => $query->where('is_active', true)])
                ->where('is_active', true)->orderBy('sort_order')->get(),
            'editingStatus' => $this->optionalModel($request, 'edit_status', TicketStatus::class),
            'editingImpact' => $this->optionalModel($request, 'edit_impact', ImpactLevel::class),
            'editingUrgency' => $this->optionalModel($request, 'edit_urgency', UrgencyLevel::class),
            'editingPriority' => $this->optionalModel($request, 'edit_priority', TicketPriority::class),
            'editingTarget' => $this->optionalModel($request, 'edit_target', ServiceLevelTarget::class),
        ]);
    }

    public function storeStatus(Request $request): RedirectResponse
    {
        $validated = $this->validateStatus($request);
        $status = $this->persistStatus($request, $validated);
        $this->audit($request, 'ticket_status_created', $status->id, $status->name);

        return $this->redirectTo('estados', 'Estado de ticket registrado.');
    }

    public function updateStatus(Request $request, TicketStatus $status): RedirectResponse
    {
        $validated = $this->validateStatus($request, $status);
        $this->guardStatusContinuity($request, $status);
        $this->persistStatus($request, $validated, $status);
        $this->audit($request, 'ticket_status_updated', $status->id, $status->name);

        return $this->redirectTo('estados', 'Estado de ticket actualizado.');
    }

    public function storeImpact(Request $request): RedirectResponse
    {
        $validated = $this->validateScale($request, 'impact_levels');
        $impact = ImpactLevel::create($this->scalePayload($request, $validated));
        $this->audit($request, 'impact_level_created', $impact->id, $impact->name);

        return $this->redirectTo('matriz', 'Nivel de impacto registrado.');
    }

    public function updateImpact(Request $request, ImpactLevel $impact): RedirectResponse
    {
        $validated = $this->validateScale($request, 'impact_levels', $impact->id);
        $impact->update($this->scalePayload($request, $validated));
        $this->audit($request, 'impact_level_updated', $impact->id, $impact->name);

        return $this->redirectTo('matriz', 'Nivel de impacto actualizado.');
    }

    public function storeUrgency(Request $request): RedirectResponse
    {
        $validated = $this->validateScale($request, 'urgency_levels');
        $urgency = UrgencyLevel::create($this->scalePayload($request, $validated));
        $this->audit($request, 'urgency_level_created', $urgency->id, $urgency->name);

        return $this->redirectTo('matriz', 'Nivel de urgencia registrado.');
    }

    public function updateUrgency(Request $request, UrgencyLevel $urgency): RedirectResponse
    {
        $validated = $this->validateScale($request, 'urgency_levels', $urgency->id);
        $urgency->update($this->scalePayload($request, $validated));
        $this->audit($request, 'urgency_level_updated', $urgency->id, $urgency->name);

        return $this->redirectTo('matriz', 'Nivel de urgencia actualizado.');
    }

    public function storePriority(Request $request): RedirectResponse
    {
        $validated = $this->validatePriority($request);
        $priority = TicketPriority::create($this->priorityPayload($request, $validated));
        $this->audit($request, 'ticket_priority_created', $priority->id, $priority->name);

        return $this->redirectTo('prioridades', 'Prioridad registrada.');
    }

    public function updatePriority(Request $request, TicketPriority $priority): RedirectResponse
    {
        $validated = $this->validatePriority($request, $priority);
        $priority->update($this->priorityPayload($request, $validated));
        $this->audit($request, 'ticket_priority_updated', $priority->id, $priority->name);

        return $this->redirectTo('prioridades', 'Prioridad actualizada.');
    }

    public function updateMatrix(Request $request): RedirectResponse
    {
        $request->validate(['matrix' => ['required', 'array']]);
        $impacts = ImpactLevel::where('is_active', true)->pluck('id');
        $urgencies = UrgencyLevel::where('is_active', true)->pluck('id');
        $validPriorities = TicketPriority::where('is_active', true)->pluck('id')->all();

        DB::transaction(function () use ($request, $impacts, $urgencies, $validPriorities): void {
            foreach ($impacts as $impactId) {
                foreach ($urgencies as $urgencyId) {
                    $priorityId = data_get($request->input('matrix'), $impactId.'.'.$urgencyId);
                    if (! in_array((int) $priorityId, $validPriorities, true)) {
                        throw ValidationException::withMessages([
                            "matrix.$impactId.$urgencyId" => 'Selecciona una prioridad válida para cada combinación.',
                        ]);
                    }
                    PriorityMatrixRule::updateOrCreate(
                        ['impact_level_id' => $impactId, 'urgency_level_id' => $urgencyId],
                        ['ticket_priority_id' => $priorityId],
                    );
                }
            }

            SecurityEvent::record('priority_matrix_updated', $request, $request->user(), [
                'combinations' => $impacts->count() * $urgencies->count(), 'performed_by' => $request->user()->id,
            ]);
        });

        return $this->redirectTo('matriz', 'Matriz de impacto y urgencia actualizada.');
    }

    public function storeTarget(Request $request): RedirectResponse
    {
        $validated = $this->validateTarget($request);
        $target = ServiceLevelTarget::create($this->targetPayload($request, $validated));
        $this->audit($request, 'sla_target_created', $target->id, 'SLA '.$target->id);

        return $this->redirectTo('sla', 'Objetivo SLA registrado.');
    }

    public function updateTarget(Request $request, ServiceLevelTarget $target): RedirectResponse
    {
        $validated = $this->validateTarget($request, $target);
        $target->update($this->targetPayload($request, $validated));
        $this->audit($request, 'sla_target_updated', $target->id, 'SLA '.$target->id);

        return $this->redirectTo('sla', 'Objetivo SLA actualizado.');
    }

    private function validateStatus(Request $request, ?TicketStatus $status = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('ticket_statuses')->ignore($status?->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sort_order' => ['required', 'integer', 'between:0,999'],
            'is_initial' => ['nullable', 'boolean'], 'is_final' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);
        if ($request->boolean('is_initial') && $request->boolean('is_final')) {
            throw ValidationException::withMessages(['is_final' => 'Un estado inicial no puede ser también final.']);
        }

        $slug = Str::slug($validated['name']);
        if (TicketStatus::where('slug', $slug)->when($status, fn ($query) => $query->whereKeyNot($status->id))->exists()) {
            throw ValidationException::withMessages(['name' => 'Ya existe un estado con un nombre equivalente.']);
        }
        $validated['slug'] = $slug;

        return $validated;
    }

    private function persistStatus(Request $request, array $validated, ?TicketStatus $status = null): TicketStatus
    {
        return DB::transaction(function () use ($request, $validated, $status): TicketStatus {
            if ($request->boolean('is_initial')) {
                TicketStatus::where('is_initial', true)->when($status, fn ($query) => $query->whereKeyNot($status->id))->update(['is_initial' => false]);
            }
            $payload = [
                'name' => trim($validated['name']), 'slug' => $validated['slug'], 'color' => strtoupper($validated['color']),
                'sort_order' => $validated['sort_order'], 'is_initial' => $request->boolean('is_initial'),
                'is_final' => $request->boolean('is_final'), 'is_active' => $request->boolean('is_active'),
            ];
            if ($status) {
                $status->update($payload);

                return $status;
            }

            return TicketStatus::create($payload);
        });
    }

    private function guardStatusContinuity(Request $request, TicketStatus $status): void
    {
        if ($status->is_initial && (! $request->boolean('is_initial') || ! $request->boolean('is_active'))
            && ! TicketStatus::whereKeyNot($status->id)->where('is_initial', true)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_initial' => 'Debe existir al menos un estado inicial activo.']);
        }
        if ($status->is_final && (! $request->boolean('is_final') || ! $request->boolean('is_active'))
            && ! TicketStatus::whereKeyNot($status->id)->where('is_final', true)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['is_final' => 'Debe existir al menos un estado final activo.']);
        }
    }

    private function validateScale(Request $request, string $table, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique($table)->ignore($id)],
            'value' => ['required', 'integer', 'between:1,5', Rule::unique($table)->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'], 'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function scalePayload(Request $request, array $validated): array
    {
        return ['name' => trim($validated['name']), 'value' => $validated['value'], 'description' => $validated['description'] ?? null, 'is_active' => $request->boolean('is_active')];
    }

    private function validatePriority(Request $request, ?TicketPriority $priority = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('ticket_priorities')->ignore($priority?->id)],
            'name' => ['required', 'string', 'max:80', Rule::unique('ticket_priorities')->ignore($priority?->id)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'weight' => ['required', 'integer', 'between:1,20', Rule::unique('ticket_priorities')->ignore($priority?->id)],
            'description' => ['nullable', 'string', 'max:1000'], 'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function priorityPayload(Request $request, array $validated): array
    {
        return [
            'code' => Str::upper(trim($validated['code'])), 'name' => trim($validated['name']),
            'color' => strtoupper($validated['color']), 'weight' => $validated['weight'],
            'description' => $validated['description'] ?? null, 'is_active' => $request->boolean('is_active'),
        ];
    }

    private function validateTarget(Request $request, ?ServiceLevelTarget $target = null): array
    {
        $validated = $request->validate([
            'ticket_priority_id' => ['required', 'integer', 'exists:ticket_priorities,id'],
            'ticket_category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'ticket_subcategory_id' => ['nullable', 'integer', 'exists:ticket_subcategories,id'],
            'response_minutes' => ['required', 'integer', 'between:1,525600'],
            'escalation_minutes' => ['required', 'integer', 'between:1,525600'],
            'resolution_minutes' => ['required', 'integer', 'between:1,525600'],
            'business_hours_only' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['ticket_subcategory_id']) && empty($validated['ticket_category_id'])) {
            throw ValidationException::withMessages(['ticket_category_id' => 'Selecciona la categoría de la subcategoría.']);
        }
        if (! empty($validated['ticket_subcategory_id']) && ! TicketSubcategory::whereKey($validated['ticket_subcategory_id'])
            ->where('ticket_category_id', $validated['ticket_category_id'])->exists()) {
            throw ValidationException::withMessages(['ticket_subcategory_id' => 'La subcategoría no pertenece a la categoría seleccionada.']);
        }
        if ($validated['response_minutes'] > $validated['escalation_minutes'] || $validated['escalation_minutes'] > $validated['resolution_minutes']) {
            throw ValidationException::withMessages(['escalation_minutes' => 'Los tiempos deben cumplir: respuesta ≤ escalamiento ≤ resolución.']);
        }

        $duplicate = ServiceLevelTarget::where('ticket_priority_id', $validated['ticket_priority_id'])
            ->when($validated['ticket_category_id'] ?? null, fn ($query, $id) => $query->where('ticket_category_id', $id), fn ($query) => $query->whereNull('ticket_category_id'))
            ->when($validated['ticket_subcategory_id'] ?? null, fn ($query, $id) => $query->where('ticket_subcategory_id', $id), fn ($query) => $query->whereNull('ticket_subcategory_id'))
            ->when($target, fn ($query) => $query->whereKeyNot($target->id))->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['ticket_priority_id' => 'Ya existe un objetivo SLA para esa prioridad y clasificación.']);
        }

        return $validated;
    }

    private function targetPayload(Request $request, array $validated): array
    {
        return [
            'ticket_priority_id' => $validated['ticket_priority_id'], 'ticket_category_id' => $validated['ticket_category_id'] ?? null,
            'ticket_subcategory_id' => $validated['ticket_subcategory_id'] ?? null, 'response_minutes' => $validated['response_minutes'],
            'escalation_minutes' => $validated['escalation_minutes'], 'resolution_minutes' => $validated['resolution_minutes'],
            'business_hours_only' => $request->boolean('business_hours_only'), 'is_active' => $request->boolean('is_active'),
        ];
    }

    private function optionalModel(Request $request, string $key, string $model): mixed
    {
        return $request->integer($key) ? $model::findOrFail($request->integer($key)) : null;
    }

    private function redirectTo(string $fragment, string $message): RedirectResponse
    {
        return redirect(route('configuration.service-levels.index').'#'.$fragment)->with('success', $message);
    }

    private function audit(Request $request, string $event, int $recordId, string $recordName): void
    {
        SecurityEvent::record($event, $request, $request->user(), [
            'record_id' => $recordId, 'record_name' => $recordName, 'performed_by' => $request->user()->id,
        ]);
    }
}

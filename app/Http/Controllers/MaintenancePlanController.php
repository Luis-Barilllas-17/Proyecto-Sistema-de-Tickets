<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Equipment;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanEquipment;
use App\Models\SecurityEvent;
use App\Models\TechnicalGroup;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaintenancePlanController extends Controller
{
    public function create(): View
    {
        $this->authorize('create', MaintenancePlan::class);

        return view('maintenance.plans.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', MaintenancePlan::class);
        $validated = $this->validated($request);

        $plan = DB::transaction(function () use ($request, $validated): MaintenancePlan {
            $plan = MaintenancePlan::create($this->payload($validated) + [
                'created_by_id' => $request->user()->id,
                'updated_by_id' => $request->user()->id,
            ]);
            $this->syncEquipment($plan, $validated['equipment_ids'], $validated['first_due_date']);
            $this->syncChecklist($plan, $validated['checklist_text']);
            SecurityEvent::record('maintenance_plan_created', $request, details: [
                'maintenance_plan_id' => $plan->id, 'code' => $plan->code,
            ]);

            return $plan;
        });

        return redirect()->route('maintenance.plans.show', $plan)->with('success', 'Plan preventivo registrado.');
    }

    public function show(MaintenancePlan $plan): View
    {
        $this->authorize('view', $plan);
        $plan->load([
            'area', 'group', 'assignedUser', 'checklistItems',
            'assignments.equipment.area', 'assignments.equipment.department',
            'orders.equipment', 'orders.assignedTo', 'orders.ticket',
        ]);

        return view('maintenance.plans.show', ['plan' => $plan]);
    }

    public function edit(MaintenancePlan $plan): View
    {
        $this->authorize('update', $plan);
        $plan->load(['assignments', 'checklistItems']);

        return view('maintenance.plans.form', $this->formData($plan));
    }

    public function update(Request $request, MaintenancePlan $plan): RedirectResponse
    {
        $this->authorize('update', $plan);
        $validated = $this->validated($request, $plan);

        DB::transaction(function () use ($request, $plan, $validated): void {
            $plan->update($this->payload($validated) + ['updated_by_id' => $request->user()->id]);
            $this->syncEquipment($plan, $validated['equipment_ids'], $validated['first_due_date']);
            $this->syncChecklist($plan, $validated['checklist_text']);
            SecurityEvent::record('maintenance_plan_updated', $request, details: [
                'maintenance_plan_id' => $plan->id, 'code' => $plan->code,
            ]);
        });

        return redirect()->route('maintenance.plans.show', $plan)->with('success', 'Plan preventivo actualizado.');
    }

    public function reschedule(Request $request, MaintenancePlan $plan, MaintenancePlanEquipment $assignment): RedirectResponse
    {
        $this->authorize('schedule', $plan);
        abort_unless($assignment->maintenance_plan_id === $plan->id, 404);
        $validated = $request->validate([
            'next_due_date' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $assignment->update([
            'next_due_date' => $validated['next_due_date'],
            'notes' => $this->clean($validated['notes'] ?? null),
            'is_active' => $request->boolean('is_active'),
        ]);
        SecurityEvent::record('maintenance_schedule_updated', $request, details: [
            'maintenance_plan_id' => $plan->id,
            'equipment_id' => $assignment->equipment_id,
            'next_due_date' => $validated['next_due_date'],
        ]);

        return back()->with('success', 'Programación del equipo actualizada.');
    }

    private function validated(Request $request, ?MaintenancePlan $plan = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[A-Z0-9][A-Z0-9.-]*$/', Rule::unique('maintenance_plans', 'code')->ignore($plan?->id)],
            'name' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'frequency_months' => ['required', 'integer', Rule::in(array_keys(MaintenancePlan::FREQUENCIES))],
            'lead_days' => ['required', 'integer', 'between:0,90'],
            'duration_minutes' => ['required', 'integer', 'between:15,1440'],
            'default_start_time' => ['nullable', 'date_format:H:i'],
            'assigned_it_area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'assigned_it_group_id' => ['nullable', 'integer', Rule::exists('it_groups', 'id')->where('is_active', true)],
            'assigned_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'first_due_date' => ['required', 'date_format:Y-m-d'],
            'equipment_ids' => ['required', 'array', 'min:1', 'max:500'],
            'equipment_ids.*' => ['integer', Rule::exists('equipment', 'id')->where('is_active', true)],
            'checklist_text' => ['required', 'string', 'max:10000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $items = $this->checklistLines($validated['checklist_text']);
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['checklist_text' => 'Registra al menos una actividad de verificación.']);
        }
        if ($items->count() > 50) {
            throw ValidationException::withMessages(['checklist_text' => 'La lista no puede contener más de 50 actividades.']);
        }
        if (! empty($validated['assigned_it_group_id'])) {
            $group = TechnicalGroup::findOrFail($validated['assigned_it_group_id']);
            if (! empty($validated['assigned_it_area_id']) && $group->area_id !== (int) $validated['assigned_it_area_id']) {
                throw ValidationException::withMessages(['assigned_it_group_id' => 'El grupo no pertenece al área de IT seleccionada.']);
            }
        }
        if (! empty($validated['assigned_user_id']) && ! empty($validated['assigned_it_group_id'])
            && ! TechnicalGroup::findOrFail($validated['assigned_it_group_id'])->members()->whereKey($validated['assigned_user_id'])->exists()) {
            throw ValidationException::withMessages(['assigned_user_id' => 'El técnico no pertenece al grupo seleccionado.']);
        }

        return $validated;
    }

    private function payload(array $validated): array
    {
        return [
            'code' => $validated['code'],
            'name' => $this->clean($validated['name']),
            'description' => $this->clean($validated['description']),
            'frequency_months' => $validated['frequency_months'],
            'lead_days' => $validated['lead_days'],
            'duration_minutes' => $validated['duration_minutes'],
            'default_start_time' => $validated['default_start_time'] ?? null,
            'assigned_it_area_id' => $validated['assigned_it_area_id'] ?? null,
            'assigned_it_group_id' => $validated['assigned_it_group_id'] ?? null,
            'assigned_user_id' => $validated['assigned_user_id'] ?? null,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ];
    }

    private function syncEquipment(MaintenancePlan $plan, array $equipmentIds, string $firstDueDate): void
    {
        $ids = collect($equipmentIds)->map(fn ($id) => (int) $id)->unique()->values();
        $plan->assignments()->whereNotIn('equipment_id', $ids)->update(['is_active' => false]);
        foreach ($ids as $equipmentId) {
            $assignment = $plan->assignments()->firstOrNew(['equipment_id' => $equipmentId]);
            if (! $assignment->exists) {
                $assignment->start_date = $firstDueDate;
                $assignment->next_due_date = $firstDueDate;
            }
            $assignment->is_active = true;
            $assignment->save();
        }
    }

    private function syncChecklist(MaintenancePlan $plan, string $text): void
    {
        $plan->checklistItems()->delete();
        foreach ($this->checklistLines($text)->values() as $index => $label) {
            $plan->checklistItems()->create([
                'label' => $label, 'sort_order' => ($index + 1) * 10, 'is_required' => true,
            ]);
        }
    }

    private function checklistLines(string $text)
    {
        return collect(preg_split('/\r\n|\r|\n/', $text))
            ->map(fn ($line) => mb_substr($this->clean($line) ?? '', 0, 255))
            ->filter()->unique();
    }

    private function formData(?MaintenancePlan $plan = null): array
    {
        return [
            'plan' => $plan,
            'equipment' => Equipment::with(['area', 'department'])->where('is_active', true)->where('status', '!=', 'retired')->orderBy('inventory_code')->get(),
            'itAreas' => Area::where('type', 'it')->where('is_active', true)->orderBy('name')->get(),
            'groups' => TechnicalGroup::where('is_active', true)->orderBy('name')->get(),
            'technicians' => User::where('status', 'active')->whereHas('roles', fn ($query) => $query->whereIn('slug', ['tecnico-it', 'encargado-area-it', 'jefatura-it']))->orderBy('name')->get(),
        ];
    }

    private function clean(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }
}

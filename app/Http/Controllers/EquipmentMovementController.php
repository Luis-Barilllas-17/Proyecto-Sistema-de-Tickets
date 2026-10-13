<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipmentMovementController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function store(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('move', $equipment);
        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(array_keys(EquipmentMovement::TYPES))],
            'to_area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('is_active', true)],
            'to_department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'to_responsible_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'to_location' => ['nullable', 'string', 'max:255'],
            'to_status' => ['required', Rule::in(array_keys(Equipment::STATUSES))],
            'effective_at' => ['required', 'date', 'before_or_equal:now'],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
            'authorized_by_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
        ]);

        if (! empty($validated['to_department_id']) && (empty($validated['to_area_id']) || ! Department::whereKey($validated['to_department_id'])->where('area_id', $validated['to_area_id'])->exists())) {
            throw ValidationException::withMessages(['to_department_id' => 'El departamento no pertenece al área de destino.']);
        }
        if (! empty($validated['to_responsible_id'])) {
            $responsible = User::findOrFail($validated['to_responsible_id']);
            if (! empty($validated['to_area_id']) && $responsible->area_id && $responsible->area_id !== (int) $validated['to_area_id']) {
                throw ValidationException::withMessages(['to_responsible_id' => 'El responsable no pertenece al área de destino.']);
            }
        }
        $authorizer = User::findOrFail($validated['authorized_by_id']);
        if (! $authorizer->hasPermission('inventory.assignment_manage')) {
            throw ValidationException::withMessages(['authorized_by_id' => 'El usuario seleccionado no puede autorizar movimientos de equipos.']);
        }

        $this->inventory->moveEquipment($equipment, $validated, $request->user());

        return back()->with('success', 'Movimiento registrado y ficha del equipo actualizada.');
    }
}

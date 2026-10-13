<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentLicense;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EquipmentLicenseController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function store(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('manageLicense', $equipment);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:160', Rule::unique('equipment_licenses', 'name')->where('equipment_id', $equipment->id)],
            'license_reference' => ['nullable', 'string', 'max:255'],
            'seats' => ['required', 'integer', 'between:1,1000000'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $equipment->licenses()->create([
            'name' => $this->inventory->clean($validated['name']),
            'license_reference' => $this->inventory->clean($validated['license_reference'] ?? null),
            'seats' => $validated['seats'],
            'expires_at' => $validated['expires_at'] ?? null,
            'notes' => $this->inventory->clean($validated['notes'] ?? null),
            'created_by_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Licencia asociada al equipo.');
    }

    public function destroy(Equipment $equipment, EquipmentLicense $license): RedirectResponse
    {
        if ($license->equipment_id !== $equipment->id) {
            abort(404);
        }
        $this->authorize('manageLicense', $equipment);
        $license->delete();

        return back()->with('success', 'Licencia desvinculada del equipo.');
    }
}

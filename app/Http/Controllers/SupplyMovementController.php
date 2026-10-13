<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SupplyMovementController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function store(Request $request, Supply $supply): RedirectResponse
    {
        $this->authorize('moveStock', $supply);
        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(['entry', 'exit', 'adjustment'])],
            'quantity' => ['required', 'numeric', 'not_in:0', 'between:-9999999999.99,9999999999.99'],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
            'authorized_by_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
        ]);
        if ($validated['movement_type'] !== 'adjustment' && (float) $validated['quantity'] < 0) {
            throw ValidationException::withMessages(['quantity' => 'Para entradas y salidas registra una cantidad positiva.']);
        }
        $authorizer = User::findOrFail($validated['authorized_by_id']);
        if (! $authorizer->hasPermission('inventory.stock_manage')) {
            throw ValidationException::withMessages(['authorized_by_id' => 'El usuario seleccionado no puede autorizar movimientos de existencias.']);
        }

        $this->inventory->manualSupplyMovement(
            $supply,
            $validated['movement_type'],
            (float) $validated['quantity'],
            $validated['justification'],
            $request->user(),
            $authorizer->id,
        );

        return back()->with('success', 'Movimiento aplicado y existencia actualizada.');
    }
}

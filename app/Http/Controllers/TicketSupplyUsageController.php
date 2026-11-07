<?php

namespace App\Http\Controllers;

use App\Models\Supply;
use App\Models\Ticket;
use App\Models\TicketSupplyUsage;
use App\Services\InventoryService;
use App\Services\TicketHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TicketSupplyUsageController extends Controller
{
    public function __construct(private InventoryService $inventory, private TicketHistoryService $history) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('reserveSupply', $ticket);
        $validated = $request->validate([
            'supply_id' => ['required', 'integer', Rule::exists('supplies', 'id')->where('is_active', true)],
            'quantity' => ['required', 'integer', 'between:1,9999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], ['quantity.integer' => 'La cantidad debe ser un número entero, sin decimales.']);
        $supply = Supply::findOrFail($validated['supply_id']);

        DB::transaction(function () use ($request, $ticket, $supply, $validated): void {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $this->authorize('reserveSupply', $ticket);
            $usage = $this->inventory->reserveSupply($ticket, $supply, (float) $validated['quantity'], $validated['notes'] ?? null, $request->user());
            $this->history->record($ticket, $request->user(), 'ticket_supply_reserved',
                'Insumo '.$supply->code.' reservado para la atención.', [], [
                    'supply_id' => $supply->id,
                    'quantity' => $usage->quantity_reserved,
                    'unit' => $supply->unit_label,
                ], $request);
        });

        return back()->with('success', 'Insumo reservado; se descontará al confirmar la solución.');
    }

    public function destroy(Request $request, Ticket $ticket, TicketSupplyUsage $usage): RedirectResponse
    {
        if ($usage->ticket_id !== $ticket->id) {
            abort(404);
        }
        $this->authorize('reserveSupply', $ticket);
        DB::transaction(function () use ($request, $ticket, $usage): void {
            $this->inventory->releaseUsage($usage, $request->user());
            $this->history->record($ticket, $request->user(), 'ticket_supply_released',
                'Reserva de insumo liberada antes del consumo.', ['status' => 'reserved'], ['status' => 'released'], $request);
        });

        return back()->with('success', 'Reserva de insumo liberada.');
    }
}

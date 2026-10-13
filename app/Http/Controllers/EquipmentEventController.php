<?php

namespace App\Http\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentEvent;
use App\Models\Ticket;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EquipmentEventController extends Controller
{
    public function __construct(private InventoryService $inventory) {}

    public function store(Request $request, Equipment $equipment): RedirectResponse
    {
        $this->authorize('recordTechnicalEvent', $equipment);
        $validated = $request->validate([
            'event_type' => ['required', Rule::in(array_keys(EquipmentEvent::TYPES))],
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'description' => ['required', 'string', 'min:10', 'max:15000'],
            'performed_at' => ['required', 'date', 'before_or_equal:now'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'provider' => ['nullable', 'string', 'max:180'],
            'cost' => ['nullable', 'numeric', 'between:0,999999999999.99'],
            'downtime_minutes' => ['nullable', 'integer', 'between:0,5256000'],
        ]);
        if (! empty($validated['ticket_id'])) {
            $ticket = Ticket::findOrFail($validated['ticket_id']);
            if (Gate::forUser($request->user())->denies('view', $ticket)) {
                throw ValidationException::withMessages(['ticket_id' => 'No tienes acceso al ticket seleccionado.']);
            }
        }

        $equipment->events()->create([
            'event_type' => $validated['event_type'],
            'title' => $this->inventory->clean($validated['title']),
            'description' => $this->inventory->clean($validated['description']),
            'performed_at' => $validated['performed_at'],
            'ticket_id' => $validated['ticket_id'] ?? null,
            'provider' => $this->inventory->clean($validated['provider'] ?? null),
            'cost' => $validated['cost'] ?? null,
            'downtime_minutes' => $validated['downtime_minutes'] ?? null,
            'created_by_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Actividad técnica agregada al historial del equipo.');
    }
}

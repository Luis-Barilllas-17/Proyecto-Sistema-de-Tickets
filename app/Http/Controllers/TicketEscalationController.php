<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketEscalation;
use App\Services\TicketEscalationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketEscalationController extends Controller
{
    public function __construct(private TicketEscalationService $escalations) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('escalate', $ticket);
        $validated = $request->validate([
            'level' => ['required', Rule::in(array_keys(TicketEscalation::LEVELS))],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        $this->escalations->escalate($ticket, $validated['level'], $validated['reason'], $request->user());

        return back()->with('success', 'Escalamiento registrado y responsables notificados.');
    }
}

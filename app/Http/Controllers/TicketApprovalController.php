<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Services\TicketWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketApprovalController extends Controller
{
    public function __construct(private TicketWorkflowService $workflow) {}

    public function decide(Request $request, Ticket $ticket, TicketApproval $approval): RedirectResponse
    {
        if ($approval->ticket_id !== $ticket->id) {
            abort(404);
        }

        $this->authorize('approve', $ticket);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected', 'changes_requested'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['decision'] !== 'approved' && mb_strlen(trim((string) ($validated['comment'] ?? ''))) < 10) {
            throw ValidationException::withMessages([
                'comment' => 'Explica el motivo del rechazo o de la devolución (mínimo 10 caracteres).',
            ]);
        }

        $this->workflow->decideApproval(
            $ticket,
            $approval,
            $request->user(),
            $validated['decision'],
            trim((string) ($validated['comment'] ?? '')) ?: null,
            $request,
        );

        return back()->with('success', 'La decisión de aprobación fue registrada de forma segura.');
    }
}

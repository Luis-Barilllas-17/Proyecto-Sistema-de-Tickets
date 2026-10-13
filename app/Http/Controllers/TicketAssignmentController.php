<?php

namespace App\Http\Controllers;

use App\Models\TechnicalGroup;
use App\Models\Ticket;
use App\Models\TicketAssignment;
use App\Models\User;
use App\Services\TicketHistoryService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketAssignmentController extends Controller
{
    public function __construct(
        private TicketWorkflowService $workflow,
        private TicketHistoryService $history,
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('assign', $ticket);
        $validated = $this->validatedTarget($request);
        $this->workflow->assign($ticket, $request->user(), $validated['area_id'] ?? null,
            $validated['group_id'] ?? null, $validated['assignee_id'] ?? null, $validated['reason'], 'assigned', $request);

        return back()->with('success', 'Ticket asignado al responsable seleccionado.');
    }

    public function transfer(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('transfer', $ticket);
        $validated = $this->validatedTarget($request);
        $this->workflow->assign($ticket, $request->user(), $validated['area_id'] ?? null,
            $validated['group_id'] ?? null, $validated['assignee_id'] ?? null, $validated['reason'], 'transferred', $request);

        return back()->with('success', 'Ticket transferido; el motivo quedó registrado en el historial.');
    }

    public function respond(Request $request, Ticket $ticket, TicketAssignment $assignment): RedirectResponse
    {
        if ($assignment->ticket_id !== $ticket->id) {
            abort(404);
        }

        $this->authorize('respondAssignment', $ticket);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'reason' => ['nullable', 'string', 'max:1500'],
        ]);
        if ($validated['decision'] === 'rejected' && mb_strlen(trim((string) ($validated['reason'] ?? ''))) < 10) {
            throw ValidationException::withMessages(['reason' => 'La justificación del rechazo debe contener al menos 10 caracteres.']);
        }

        DB::transaction(function () use ($request, $ticket, $assignment, $validated): void {
            $lockedTicket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $lockedAssignment = TicketAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            if ($lockedAssignment->status !== 'pending' || $lockedAssignment->to_user_id !== $request->user()->id
                || $lockedTicket->current_assignee_id !== $request->user()->id) {
                throw ValidationException::withMessages(['decision' => 'Esta asignación ya no está pendiente o fue reemplazada.']);
            }

            $accepted = $validated['decision'] === 'accepted';
            $lockedAssignment->update([
                'status' => $validated['decision'],
                'rejection_reason' => $accepted ? null : trim((string) $validated['reason']),
                'responded_at' => now(),
            ]);
            $lockedTicket->update([
                'assignment_status' => $validated['decision'],
                'current_assignee_id' => $accepted ? $lockedTicket->current_assignee_id : null,
                'ticket_status_id' => $accepted ? $this->workflow->status('en-analisis')->id : $this->workflow->status('asignado')->id,
                'first_response_at' => $accepted ? ($lockedTicket->first_response_at ?? now()) : $lockedTicket->first_response_at,
                'last_activity_at' => now(),
                'lock_version' => $lockedTicket->lock_version + 1,
            ]);
            $this->history->record($lockedTicket, $request->user(), $accepted ? 'assignment_accepted' : 'assignment_rejected',
                $accepted ? 'El técnico aceptó la asignación.' : 'El técnico rechazó la asignación con justificación.',
                ['assignment_status' => 'pending'],
                ['assignment_status' => $validated['decision'], 'reason' => $validated['reason'] ?? null], $request);
        });

        return back()->with('success', $validated['decision'] === 'accepted' ? 'Asignación aceptada.' : 'Asignación rechazada y devuelta al grupo.');
    }

    private function validatedTarget(Request $request): array
    {
        $validated = $request->validate([
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->where(fn ($query) => $query->where('type', 'it')->where('is_active', true))],
            'group_id' => ['nullable', 'integer', Rule::exists('it_groups', 'id')->where('is_active', true)],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('status', 'active')],
            'reason' => ['required', 'string', 'min:5', 'max:1500'],
        ]);

        if (empty($validated['area_id']) && empty($validated['group_id']) && empty($validated['assignee_id'])) {
            throw ValidationException::withMessages(['area_id' => 'Selecciona al menos un área, grupo o responsable.']);
        }

        $group = ! empty($validated['group_id']) ? TechnicalGroup::findOrFail($validated['group_id']) : null;
        if ($group && ! empty($validated['area_id']) && $group->area_id !== (int) $validated['area_id']) {
            throw ValidationException::withMessages(['group_id' => 'El grupo no pertenece al área de IT seleccionada.']);
        }
        if ($group && empty($validated['area_id'])) {
            $validated['area_id'] = $group->area_id;
        }

        if (! empty($validated['assignee_id'])) {
            $assignee = User::findOrFail($validated['assignee_id']);
            $technical = $assignee->roles()->whereIn('slug', ['jefatura-it', 'encargado-area-it', 'tecnico-it', 'programador-it'])
                ->where('is_active', true)->exists();
            if (! $technical) {
                throw ValidationException::withMessages(['assignee_id' => 'El responsable debe tener un perfil técnico activo.']);
            }
            if ($group && ! $group->members()->whereKey($assignee->id)->exists()) {
                throw ValidationException::withMessages(['assignee_id' => 'El responsable seleccionado no pertenece al grupo técnico.']);
            }
        }

        return $validated;
    }
}

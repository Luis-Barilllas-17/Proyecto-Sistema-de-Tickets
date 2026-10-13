<?php

namespace App\Http\Controllers;

use App\Models\DevelopmentDeploymentAuthorization;
use App\Models\DevelopmentRequest;
use App\Models\Ticket;
use App\Services\DevelopmentRequestService;
use App\Services\TicketHistoryService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DevelopmentDeploymentController extends Controller
{
    public function __construct(
        private DevelopmentRequestService $development,
        private TicketHistoryService $history,
        private TicketWorkflowService $workflow,
    ) {}

    public function store(Request $request, DevelopmentRequest $developmentRequest): RedirectResponse
    {
        $this->authorize('requestDeployment', $developmentRequest);
        $issues = $this->development->deploymentReadiness($developmentRequest);
        if ($issues->isNotEmpty()) {
            throw ValidationException::withMessages(['deployment' => $issues->all()]);
        }
        $validated = $request->validate([
            'version' => ['required', 'string', 'min:1', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/'],
            'release_notes' => ['required', 'string', 'min:20', 'max:15000'],
            'deployment_plan' => ['required', 'string', 'min:20', 'max:15000'],
            'rollback_plan' => ['required', 'string', 'min:20', 'max:15000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);

        DB::transaction(function () use ($request, $developmentRequest, $validated): void {
            $authorization = $developmentRequest->deploymentAuthorizations()->create([
                'version' => $validated['version'],
                'environment' => 'production',
                'release_notes' => $this->development->clean($validated['release_notes']),
                'deployment_plan' => $this->development->clean($validated['deployment_plan']),
                'rollback_plan' => $this->development->clean($validated['rollback_plan']),
                'scheduled_at' => $validated['scheduled_at'] ?? null,
                'requested_by_id' => $request->user()->id,
                'requested_at' => now(),
                'status' => 'pending',
            ]);
            $developmentRequest->update(['lifecycle_status' => 'deployment_pending']);
            $developmentRequest->ticket->update(['last_activity_at' => now(), 'lock_version' => $developmentRequest->ticket->lock_version + 1]);
            $this->history->record($developmentRequest->ticket, $request->user(), 'deployment_authorization_requested',
                'Autorización de despliegue a producción solicitada para la versión '.$authorization->version.'.', [], [
                    'scheduled_at' => $authorization->scheduled_at?->toIso8601String(),
                ], $request);
        });

        return back()->with('success', 'Solicitud de despliegue enviada a Jefatura de IT.');
    }

    public function decide(
        Request $request,
        DevelopmentRequest $developmentRequest,
        DevelopmentDeploymentAuthorization $authorization,
    ): RedirectResponse {
        if ($authorization->development_request_id !== $developmentRequest->id) {
            abort(404);
        }
        $this->authorize('authorizeDeployment', $developmentRequest);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['authorized', 'rejected'])],
            'decision_comment' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if ($validated['decision'] === 'authorized') {
            $issues = $this->development->deploymentReadiness($developmentRequest, true);
            if ($issues->isNotEmpty()) {
                throw ValidationException::withMessages(['decision' => $issues->all()]);
            }
        }

        DB::transaction(function () use ($request, $developmentRequest, $authorization, $validated): void {
            $lockedAuthorization = DevelopmentDeploymentAuthorization::whereKey($authorization->id)->lockForUpdate()->firstOrFail();
            $ticket = Ticket::whereKey($developmentRequest->ticket_id)->lockForUpdate()->firstOrFail();
            if ($lockedAuthorization->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Esta autorización ya fue resuelta.']);
            }

            $authorized = $validated['decision'] === 'authorized';
            $lockedAuthorization->update([
                'status' => $validated['decision'],
                'decided_by_id' => $request->user()->id,
                'decided_at' => now(),
                'decision_comment' => $this->development->clean($validated['decision_comment']),
            ]);
            $developmentRequest->update(['lifecycle_status' => $authorized ? 'closed' : 'deployment_rejected']);

            if ($authorized) {
                $ticket->update([
                    'ticket_status_id' => $this->workflow->status('cerrado')->id,
                    'solution' => $ticket->solution ?: 'Despliegue a producción autorizado para la versión '.$lockedAuthorization->version.'.',
                    'solution_by_id' => $request->user()->id,
                    'solved_at' => now(),
                    'closed_by_id' => $request->user()->id,
                    'closed_at' => now(),
                    'last_activity_at' => now(),
                    'lock_version' => $ticket->lock_version + 1,
                ]);
            } else {
                $ticket->update([
                    'ticket_status_id' => $this->workflow->status('en-proceso')->id,
                    'last_activity_at' => now(),
                    'lock_version' => $ticket->lock_version + 1,
                ]);
            }

            $this->history->record($ticket, $request->user(), $authorized ? 'deployment_authorized' : 'deployment_rejected',
                $authorized
                    ? 'Jefatura de IT autorizó el despliegue a producción y el cierre del ticket.'
                    : 'Jefatura de IT rechazó el despliegue; el ticket volvió a proceso.',
                ['status' => 'pending'], [
                    'status' => $validated['decision'],
                    'version' => $lockedAuthorization->version,
                    'comment' => $validated['decision_comment'],
                ], $request);
        });

        return back()->with('success', $validated['decision'] === 'authorized'
            ? 'Despliegue autorizado y ticket de desarrollo cerrado.' : 'Despliegue rechazado; el ticket regresó a proceso.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketThirdPartyDependency;
use App\Services\TicketHistoryService;
use App\Services\TicketWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TicketThirdPartyController extends Controller
{
    public function __construct(
        private TicketWorkflowService $workflow,
        private TicketHistoryService $history,
    ) {}

    public function store(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('manageThirdParty', $ticket);
        $validated = $request->validate([
            'provider' => ['required', 'string', 'min:2', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ]);

        DB::transaction(function () use ($request, $ticket, $validated): void {
            $dependency = $ticket->thirdPartyDependencies()->create([
                'provider' => $this->clean($validated['provider']),
                'reference' => $this->clean($validated['reference'] ?? null),
                'description' => $this->clean($validated['description']),
                'status' => 'pending', 'requested_at' => now(), 'due_at' => $validated['due_at'] ?? null,
                'created_by_id' => $request->user()->id,
            ]);
            $ticket->update([
                'ticket_status_id' => $this->workflow->status('pendiente-tercero')->id,
                'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
            ]);
            $this->history->record($ticket, $request->user(), 'third_party_requested',
                'Dependencia registrada con el proveedor '.$dependency->provider.'.', [], [
                    'provider' => $dependency->provider, 'reference' => $dependency->reference,
                    'due_at' => $dependency->due_at?->toIso8601String(),
                ], $request);
        });

        return back()->with('success', 'Dependencia externa registrada; el ticket quedó pendiente del tercero.');
    }

    public function resolve(Request $request, Ticket $ticket, TicketThirdPartyDependency $dependency): RedirectResponse
    {
        abort_unless($dependency->ticket_id === $ticket->id, 404);
        $this->authorize('manageThirdParty', $ticket);
        $validated = $request->validate(['resolution' => ['required', 'string', 'min:10', 'max:5000']]);

        DB::transaction(function () use ($request, $ticket, $dependency, $validated): void {
            $dependency = TicketThirdPartyDependency::whereKey($dependency->id)->lockForUpdate()->firstOrFail();
            abort_if($dependency->status !== 'pending', 409, 'La dependencia ya fue resuelta.');
            $dependency->update([
                'status' => 'resolved', 'resolution' => $this->clean($validated['resolution']),
                'resolved_at' => now(), 'resolved_by_id' => $request->user()->id,
            ]);
            if (! $ticket->thirdPartyDependencies()->where('status', 'pending')->exists()) {
                $ticket->update([
                    'ticket_status_id' => $this->workflow->status('en-proceso')->id,
                    'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
                ]);
            }
            $this->history->record($ticket, $request->user(), 'third_party_resolved',
                'Dependencia con '.$dependency->provider.' resuelta.', [], [
                    'provider' => $dependency->provider, 'resolution' => $dependency->resolution,
                ], $request);
        });

        return back()->with('success', 'Dependencia externa resuelta y atención reanudada.');
    }

    private function clean(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }
}

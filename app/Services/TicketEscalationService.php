<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEscalation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketEscalationService
{
    public function __construct(
        private TicketHistoryService $history,
        private SystemNotificationService $notifications,
    ) {}

    public function escalate(
        Ticket $ticket,
        string $level,
        string $reason,
        ?User $actor = null,
        string $source = 'manual',
        ?string $triggerKey = null,
    ): TicketEscalation {
        if (! array_key_exists($level, TicketEscalation::LEVELS)) {
            throw ValidationException::withMessages(['level' => 'El nivel de escalamiento no es válido.']);
        }

        return DB::transaction(function () use ($ticket, $level, $reason, $actor, $source, $triggerKey): TicketEscalation {
            $ticket = Ticket::with(['currentArea.head', 'currentGroup.coordinator'])->whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $recipients = $this->recipients($ticket, $level);
            if ($actor) {
                $recipients = $recipients->reject(fn (User $user) => $user->id === $actor->id)->values();
            }
            if ($recipients->isEmpty()) {
                throw ValidationException::withMessages(['level' => 'No existe un responsable activo configurado para este escalamiento.']);
            }

            $triggerKey ??= 'manual-'.Str::uuid();
            $escalation = TicketEscalation::firstOrCreate([
                'ticket_id' => $ticket->id,
                'level' => $level,
                'trigger_key' => $triggerKey,
            ], [
                'source' => $source,
                'reason' => mb_substr(trim($reason), 0, 2000),
                'escalated_to_user_id' => $recipients->first()->id,
                'created_by_id' => $actor?->id,
                'escalated_at' => now(),
            ]);

            if ($escalation->wasRecentlyCreated) {
                $this->history->record($ticket, $actor, 'ticket_escalated',
                    'Ticket escalado a '.$escalation->level_label.'.', [], [
                        'level' => $level, 'reason' => $reason, 'source' => $source,
                    ]);
                $this->notifications->sendToUsers($recipients, [
                    'category' => 'alert', 'severity' => 'critical',
                    'title' => 'Ticket escalado: '.$ticket->number,
                    'message' => trim($reason),
                    'ticket_id' => $ticket->id, 'ticket_number' => $ticket->number,
                    'url' => route('tickets.show', $ticket), 'event' => 'ticket_escalated',
                ]);
            }

            return $escalation;
        });
    }

    private function recipients(Ticket $ticket, string $level): Collection
    {
        if ($level === 'it_head') {
            return User::where('status', 'active')->whereHas('roles', fn ($query) => $query
                ->where('slug', 'jefatura-it')->where('is_active', true))->get();
        }

        $managers = collect([$ticket->currentGroup?->coordinator, $ticket->currentArea?->head])
            ->filter(fn ($user) => $user?->isActive() && ($user->hasRole('encargado-area-it') || $user->hasRole('jefatura-it')))
            ->unique('id')->values();

        return $managers->isNotEmpty() ? $managers : User::where('status', 'active')->whereHas('roles', fn ($query) => $query
            ->where('slug', 'jefatura-it')->where('is_active', true))->get();
    }
}

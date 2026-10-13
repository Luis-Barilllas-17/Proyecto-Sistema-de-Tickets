<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use App\Notifications\SystemEventMailNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SystemNotificationService
{
    public function fromTicketHistory(TicketHistory $history): void
    {
        $definition = $this->eventDefinition($history);
        if (! $definition) {
            return;
        }

        $ticket = $history->ticket()->with([
            'requester', 'currentAssignee', 'currentGroup.members', 'currentArea.head',
            'approvals.approver', 'approvals.approverRole.users',
        ])->firstOrFail();
        $recipients = $this->recipients($ticket, $history->event);
        if (! ($definition['include_actor'] ?? false) && $history->user_id) {
            $recipients = $recipients->reject(fn (User $user) => $user->id === $history->user_id);
        }

        $this->sendToUsers($recipients, [
            'category' => 'ticket',
            'severity' => $definition['severity'] ?? 'info',
            'title' => $definition['title'],
            'message' => $definition['message'],
            'ticket_id' => $ticket->id,
            'ticket_number' => $ticket->number,
            'url' => route('tickets.show', $ticket),
            'event' => $history->event,
            'history_id' => $history->id,
        ]);
    }

    public function sendToUsers(iterable $users, array $payload): int
    {
        $payload = $this->normalizedPayload($payload);
        $recipients = collect($users)->filter(fn ($user) => $user instanceof User && $user->isActive())
            ->unique('id')->values();

        foreach ($recipients as $user) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => SystemEventMailNotification::class,
                'data' => $payload,
            ]);
            $this->queueMailAfterCommit($user, $payload);
        }

        return $recipients->count();
    }

    private function recipients(Ticket $ticket, string $event): Collection
    {
        $requesterEvents = [
            'ticket_created', 'ticket_submitted', 'approval_granted', 'approval_completed',
            'approval_rejected', 'approval_changes_requested', 'ticket_assigned', 'ticket_transferred',
            'assignment_accepted', 'assignment_rejected', 'public_comment_added', 'status_changed',
            'solution_registered', 'solution_accepted', 'solution_rejected', 'ticket_reopened',
            'ticket_cancelled', 'maintenance_started', 'maintenance_completed',
            'third_party_requested', 'third_party_resolved', 'ticket_escalated',
        ];
        $recipients = collect();
        if (in_array($event, $requesterEvents, true)) {
            $recipients->push($ticket->requester);
        }

        if (in_array($event, ['ticket_submitted', 'approval_granted'], true)) {
            foreach ($ticket->approvals->where('status', 'pending') as $approval) {
                if ($approval->approver) {
                    $recipients->push($approval->approver);
                }
                foreach ($approval->approverRole?->users ?? [] as $user) {
                    $recipients->push($user);
                }
            }
        }

        if (in_array($event, [
            'ticket_submitted', 'ticket_assigned', 'ticket_transferred', 'public_comment_added',
            'status_changed', 'solution_rejected', 'ticket_reopened',
        ], true)) {
            if ($ticket->currentAssignee) {
                $recipients->push($ticket->currentAssignee);
            } else {
                $recipients = $recipients->merge($ticket->currentGroup?->members ?? []);
            }
        }

        return $recipients->filter();
    }

    private function eventDefinition(TicketHistory $history): ?array
    {
        $number = $history->ticket?->number ?? 'Ticket';

        return match ($history->event) {
            'ticket_created' => ['title' => 'Ticket registrado', 'message' => "$number fue registrado correctamente.", 'include_actor' => true],
            'ticket_submitted' => ['title' => 'Ticket enviado', 'message' => "$number ingresó al flujo de atención.", 'include_actor' => true],
            'approval_granted' => ['title' => 'Aprobación registrada', 'message' => "$number avanzó al siguiente nivel de aprobación."],
            'approval_completed' => ['title' => 'Aprobaciones completadas', 'message' => "$number completó su flujo de aprobación."],
            'approval_rejected' => ['title' => 'Ticket rechazado', 'message' => "$number fue rechazado durante la aprobación.", 'severity' => 'warning'],
            'approval_changes_requested' => ['title' => 'Corrección solicitada', 'message' => "$number fue devuelto para corrección.", 'severity' => 'warning'],
            'ticket_assigned' => ['title' => 'Nueva asignación', 'message' => "$number fue asignado para atención."],
            'ticket_transferred' => ['title' => 'Ticket transferido', 'message' => "$number fue transferido a otro responsable."],
            'assignment_accepted' => ['title' => 'Asignación aceptada', 'message' => "El responsable aceptó la atención de $number."],
            'assignment_rejected' => ['title' => 'Asignación rechazada', 'message' => "La asignación de $number fue rechazada con justificación.", 'severity' => 'warning'],
            'public_comment_added' => ['title' => 'Nuevo comentario', 'message' => "$number recibió un comentario visible."],
            'status_changed' => ['title' => 'Cambio de estado', 'message' => "$number cambió de estado."],
            'solution_registered' => ['title' => 'Solución disponible', 'message' => "$number tiene una solución pendiente de aceptación."],
            'solution_accepted' => ['title' => 'Servicio aceptado', 'message' => "$number fue aceptado y cerrado."],
            'solution_rejected' => ['title' => 'Solución rechazada', 'message' => "$number regresó al equipo de IT.", 'severity' => 'warning'],
            'ticket_reopened' => ['title' => 'Ticket reabierto', 'message' => "$number fue reabierto para continuar la atención.", 'severity' => 'warning'],
            'ticket_cancelled' => ['title' => 'Ticket cancelado', 'message' => "$number fue cancelado.", 'severity' => 'warning'],
            'maintenance_started' => ['title' => 'Mantenimiento iniciado', 'message' => "Inició el mantenimiento asociado con $number."],
            'maintenance_completed' => ['title' => 'Mantenimiento completado', 'message' => "Finalizó el mantenimiento asociado con $number."],
            'third_party_requested' => ['title' => 'Dependencia con tercero', 'message' => "$number quedó pendiente de una gestión externa.", 'severity' => 'warning'],
            'third_party_resolved' => ['title' => 'Gestión externa resuelta', 'message' => "$number reanudó su atención interna."],
            default => null,
        };
    }

    private function normalizedPayload(array $payload): array
    {
        return [
            'category' => mb_substr((string) ($payload['category'] ?? 'system'), 0, 40),
            'severity' => in_array($payload['severity'] ?? null, ['info', 'success', 'warning', 'critical'], true) ? $payload['severity'] : 'info',
            'title' => mb_substr(strip_tags((string) ($payload['title'] ?? 'Notificación')), 0, 180),
            'message' => mb_substr(strip_tags((string) ($payload['message'] ?? '')), 0, 1000),
            'ticket_id' => isset($payload['ticket_id']) ? (int) $payload['ticket_id'] : null,
            'ticket_number' => isset($payload['ticket_number']) ? mb_substr((string) $payload['ticket_number'], 0, 30) : null,
            'url' => filter_var($payload['url'] ?? null, FILTER_VALIDATE_URL) ? $payload['url'] : route('dashboard'),
            'event' => mb_substr((string) ($payload['event'] ?? 'system'), 0, 60),
            'history_id' => isset($payload['history_id']) ? (int) $payload['history_id'] : null,
        ];
    }

    private function queueMailAfterCommit(User $user, array $payload): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $send = function () use ($user, $payload): void {
            try {
                $user->notify(new SystemEventMailNotification($payload));
            } catch (\Throwable $exception) {
                Log::warning('No fue posible enviar una notificación por correo.', [
                    'user_id' => $user->id, 'event' => $payload['event'], 'error' => $exception->getMessage(),
                ]);
            }
        };
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($send);
        } else {
            $send();
        }
    }
}

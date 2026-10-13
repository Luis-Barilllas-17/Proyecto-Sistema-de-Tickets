<?php

namespace App\Services;

use App\Models\MaintenanceOrder;
use App\Models\SystemAlert;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OperationalAlertService
{
    public function __construct(
        private SystemNotificationService $notifications,
        private TicketEscalationService $escalations,
    ) {}

    public function process(): int
    {
        $created = 0;
        $this->resolveCompletedAlerts();

        Ticket::with(['status', 'requester', 'currentAssignee', 'currentGroup.members', 'currentGroup.coordinator', 'currentArea.head'])
            ->where('is_draft', false)->whereHas('status', fn ($query) => $query->where('is_final', false))
            ->orderBy('id')->chunkById(200, function ($tickets) use (&$created): void {
                foreach ($tickets as $ticket) {
                    if (! $ticket->first_response_at && $ticket->response_due_at) {
                        if ($ticket->response_due_at->isPast()) {
                            $created += $this->createTicketAlert($ticket, 'response_overdue', 'critical',
                                'Respuesta inicial vencida', 'El tiempo objetivo de respuesta inicial fue superado.', $ticket->response_due_at);
                            $this->automaticEscalation($ticket, 'area_manager', 'response-'.$ticket->response_due_at->timestamp,
                                'SLA de respuesta inicial vencido.');
                        } elseif ($ticket->response_due_at->lte(now()->addHour())) {
                            $created += $this->createTicketAlert($ticket, 'response_near_due', 'warning',
                                'Respuesta inicial próxima a vencer', 'Queda menos de una hora para responder el ticket.', $ticket->response_due_at);
                        }
                    }
                    if (! $ticket->solved_at && $ticket->resolution_due_at) {
                        if ($ticket->resolution_due_at->isPast()) {
                            $created += $this->createTicketAlert($ticket, 'resolution_overdue', 'critical',
                                'Resolución vencida', 'El tiempo objetivo de resolución fue superado.', $ticket->resolution_due_at);
                            $this->automaticEscalation($ticket, 'it_head', 'resolution-'.$ticket->resolution_due_at->timestamp,
                                'SLA de resolución vencido.');
                        } elseif ($ticket->resolution_due_at->lte(now()->addHours(4))) {
                            $created += $this->createTicketAlert($ticket, 'resolution_near_due', 'warning',
                                'Resolución próxima a vencer', 'El ticket alcanzará su límite de resolución en menos de cuatro horas.', $ticket->resolution_due_at);
                        }
                    }
                    foreach ($ticket->thirdPartyDependencies()->where('status', 'pending')->where('due_at', '<', now())->get() as $dependency) {
                        $created += $this->createTicketAlert($ticket, 'third_party_overdue', 'critical',
                            'Dependencia externa vencida', 'El compromiso con '.$dependency->provider.' superó su fecha prevista.',
                            $dependency->due_at, 'third-party-'.$dependency->id);
                    }
                }
            });

        MaintenanceOrder::with(['equipment', 'plan.group.coordinator', 'plan.area.head', 'assignedTo'])
            ->whereNotIn('status', ['completed', 'cancelled'])->whereDate('scheduled_for', '<=', today()->addDays(3))
            ->orderBy('id')->chunkById(200, function ($orders) use (&$created): void {
                foreach ($orders as $order) {
                    $overdue = $order->scheduled_for->isBefore(today());
                    $fingerprint = 'maintenance:'.$order->id.':'.($overdue ? 'overdue' : 'upcoming').':'.$order->scheduled_for->format('Ymd');
                    $alert = SystemAlert::firstOrCreate(['fingerprint' => $fingerprint], [
                        'maintenance_order_id' => $order->id,
                        'type' => $overdue ? 'maintenance_overdue' : 'maintenance_upcoming',
                        'severity' => $overdue ? 'critical' : 'warning',
                        'title' => $overdue ? 'Mantenimiento vencido' : 'Mantenimiento próximo',
                        'message' => $order->number.' · '.$order->equipment->inventory_code.' · '.$order->plan->name,
                        'status' => 'open', 'due_at' => $order->scheduled_for, 'detected_at' => now(),
                    ]);
                    if ($alert->wasRecentlyCreated) {
                        $created++;
                        $recipients = collect([$order->assignedTo, $order->plan->group?->coordinator, $order->plan->area?->head])->filter();
                        $this->notifications->sendToUsers($recipients, [
                            'category' => 'maintenance', 'severity' => $alert->severity, 'title' => $alert->title,
                            'message' => $alert->message, 'url' => route('maintenance.orders.show', $order),
                            'event' => $alert->type,
                        ]);
                    }
                }
            });

        return $created;
    }

    private function createTicketAlert(
        Ticket $ticket,
        string $type,
        string $severity,
        string $title,
        string $message,
        $dueAt,
        ?string $suffix = null,
    ): int {
        $fingerprint = 'ticket:'.$ticket->id.':'.$type.':'.($suffix ?: $dueAt->format('YmdHi'));
        $alert = SystemAlert::firstOrCreate(['fingerprint' => $fingerprint], [
            'ticket_id' => $ticket->id, 'type' => $type, 'severity' => $severity,
            'title' => $title, 'message' => $message, 'status' => 'open',
            'due_at' => $dueAt, 'detected_at' => now(),
        ]);
        if (! $alert->wasRecentlyCreated) {
            return 0;
        }

        $this->notifications->sendToUsers($this->ticketRecipients($ticket, $severity === 'critical'), [
            'category' => 'alert', 'severity' => $severity,
            'title' => $title.' · '.$ticket->number,
            'message' => $message,
            'ticket_id' => $ticket->id, 'ticket_number' => $ticket->number,
            'url' => route('tickets.show', $ticket), 'event' => $type,
        ]);

        return 1;
    }

    private function ticketRecipients(Ticket $ticket, bool $includeSupervisors): Collection
    {
        $recipients = collect([$ticket->currentAssignee]);
        if (! $ticket->currentAssignee) {
            $recipients = $recipients->merge($ticket->currentGroup?->members ?? []);
        }
        if ($includeSupervisors) {
            $recipients->push($ticket->currentGroup?->coordinator, $ticket->currentArea?->head);
        }

        return $recipients->filter()->unique('id')->values();
    }

    private function automaticEscalation(Ticket $ticket, string $level, string $trigger, string $reason): void
    {
        try {
            $this->escalations->escalate($ticket, $level, $reason, null, 'automatic', $trigger);
        } catch (ValidationException) {
            // The alert remains visible even when the organization has no configured recipient.
        }
    }

    private function resolveCompletedAlerts(): void
    {
        SystemAlert::where('status', 'open')->whereNotNull('ticket_id')
            ->whereHas('ticket', fn ($query) => $query->whereNotNull('solved_at')->orWhereHas('status', fn ($status) => $status->where('is_final', true)))
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
        SystemAlert::where('status', 'open')->whereNotNull('maintenance_order_id')
            ->whereHas('maintenanceOrder', fn ($query) => $query->whereIn('status', ['completed', 'cancelled']))
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
    }
}

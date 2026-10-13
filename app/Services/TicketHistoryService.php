<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Http\Request;

class TicketHistoryService
{
    public function __construct(private SystemNotificationService $notifications) {}

    public function record(
        Ticket $ticket,
        ?User $user,
        string $event,
        string $description,
        array $from = [],
        array $to = [],
        ?Request $request = null,
    ): TicketHistory {
        $history = $ticket->histories()->create([
            'user_id' => $user?->id,
            'event' => $event,
            'description' => mb_substr($description, 0, 1000),
            'from_values' => $from ?: null,
            'to_values' => $to ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
        ]);

        $this->notifications->fromTicketHistory($history->load('ticket'));

        return $history;
    }
}

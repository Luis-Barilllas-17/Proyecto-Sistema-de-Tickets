<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\TicketLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketLookupController extends Controller
{
    public function supplies(Request $request, Ticket $ticket, TicketLookupService $lookups): JsonResponse
    {
        $this->authorize('reserveSupply', $ticket);

        return response()->json($lookups->supplies($this->query($request), $ticket));
    }

    public function requesters(Request $request, TicketLookupService $lookups): JsonResponse
    {
        $this->authorize('create', Ticket::class);
        $this->authorize('createInternal', Ticket::class);

        return response()->json($lookups->requesters($this->query($request)));
    }

    public function equipment(Request $request, TicketLookupService $lookups): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        return response()->json($lookups->equipment($this->query($request)));
    }

    private function query(Request $request): string
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return trim($validated['q'] ?? '');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(private TicketHistoryService $history) {}

    public function download(Request $request, Ticket $ticket, TicketAttachment $attachment): BinaryFileResponse
    {
        $this->ensureBelongsToTicket($ticket, $attachment);
        $this->authorize('view', $ticket);
        if ($attachment->is_internal && ! ($request->user()->hasPermission('tickets.internal_note') || $request->user()->hasPermission('tickets.view_all'))) {
            abort(403);
        }
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

        return response()->download(
            Storage::disk($attachment->disk)->path($attachment->path),
            $attachment->original_name,
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function destroy(Request $request, Ticket $ticket, TicketAttachment $attachment): RedirectResponse
    {
        $this->ensureBelongsToTicket($ticket, $attachment);
        $this->authorize('deleteAttachment', $ticket);
        if ($attachment->context !== 'initial' || $attachment->uploaded_by_id !== $request->user()->id) {
            abort(403);
        }

        $name = $attachment->original_name;
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
        $this->history->record($ticket, $request->user(), 'attachment_deleted', 'Adjunto eliminado del borrador.',
            ['file' => $name], [], $request);

        return back()->with('success', 'Adjunto eliminado del borrador.');
    }

    private function ensureBelongsToTicket(Ticket $ticket, TicketAttachment $attachment): void
    {
        if ($attachment->ticket_id !== $ticket->id) {
            abort(404);
        }
    }
}

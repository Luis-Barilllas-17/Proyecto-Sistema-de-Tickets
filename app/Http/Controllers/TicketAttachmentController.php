<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketAttachmentPreviewService;
use App\Services\TicketHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TicketAttachmentController extends Controller
{
    public function __construct(private TicketHistoryService $history) {}

    public function download(Request $request, Ticket $ticket, TicketAttachment $attachment): BinaryFileResponse
    {
        $this->authorizeAttachment($request, $ticket, $attachment);

        return response()->download(
            Storage::disk($attachment->disk)->path($attachment->path),
            $attachment->original_name,
            ['Content-Type' => 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'],
        )->setPrivate();
    }

    public function preview(Request $request, Ticket $ticket, TicketAttachment $attachment, TicketAttachmentPreviewService $previews): JsonResponse
    {
        $this->authorizeAttachment($request, $ticket, $attachment);
        $data = $previews->preview($attachment);
        if (in_array($data['type'], ['image', 'pdf'], true)) {
            $data['url'] = route('tickets.attachments.inline', [$ticket, $attachment]);
        }

        return response()->json($data)->header('Cache-Control', 'private, no-store');
    }

    public function inline(Request $request, Ticket $ticket, TicketAttachment $attachment, TicketAttachmentPreviewService $previews): BinaryFileResponse
    {
        $this->authorizeAttachment($request, $ticket, $attachment);
        $type = $previews->inlineType($attachment);
        abort_unless($type, 415);

        return response()->file(Storage::disk($attachment->disk)->path($attachment->path), [
            'Content-Type' => $type, 'Cache-Control' => 'private, no-store',
        ])->setPrivate();
    }

    private function authorizeAttachment(Request $request, Ticket $ticket, TicketAttachment $attachment): void
    {
        $this->ensureBelongsToTicket($ticket, $attachment);
        $this->authorize('view', $ticket);
        if ($attachment->is_internal && ! ($request->user()->hasPermission('tickets.internal_note') || $request->user()->hasPermission('tickets.view_all'))) {
            abort(403);
        }
        if (! Storage::disk($attachment->disk)->exists($attachment->path)) {
            abort(404);
        }

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

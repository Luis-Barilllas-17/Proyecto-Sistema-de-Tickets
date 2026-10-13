<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketRelation;
use App\Models\TicketReview;
use App\Services\InventoryService;
use App\Services\TicketAttachmentService;
use App\Services\TicketHistoryService;
use App\Services\TicketWorkflowService;
use App\Support\UploadSecurityPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketCollaborationController extends Controller
{
    public function __construct(
        private TicketAttachmentService $attachments,
        private TicketHistoryService $history,
        private TicketWorkflowService $workflow,
        private InventoryService $inventory,
    ) {}

    public function comment(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('comment', $ticket);
        $validated = $request->validate($this->messageRules(), $this->fileMessages());

        DB::transaction(function () use ($request, $ticket, $validated): void {
            $comment = $ticket->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $this->clean($validated['body']),
                'is_internal' => false,
            ]);
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $request->user(), $request->file('attachments'), 'comment', false, $comment);
            }
            $ticket->update([
                'first_response_at' => $ticket->first_response_at ?? ($ticket->requester_id !== $request->user()->id ? now() : null),
                'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
            ]);
            $this->history->record($ticket, $request->user(), 'public_comment_added', 'Comentario visible agregado al ticket.', [], [], $request);
        });

        return back()->with('success', 'Comentario publicado para el solicitante y el equipo de IT.');
    }

    public function internalNote(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('internalNote', $ticket);
        $validated = $request->validate($this->messageRules(), $this->fileMessages());

        DB::transaction(function () use ($request, $ticket, $validated): void {
            $comment = $ticket->comments()->create([
                'user_id' => $request->user()->id,
                'body' => $this->clean($validated['body']),
                'is_internal' => true,
            ]);
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $request->user(), $request->file('attachments'), 'comment', true, $comment);
            }
            $ticket->update(['last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1]);
            $this->history->record($ticket, $request->user(), 'internal_note_added', 'Nota interna registrada para el personal de IT.', [], [], $request);
        });

        return back()->with('success', 'Nota interna guardada; no es visible para el solicitante.');
    }

    public function activity(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('logActivity', $ticket);
        $validated = $request->validate([
            'activity_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'activity_type' => ['required', Rule::in(array_keys(TicketActivity::TYPES))],
            'minutes_spent' => ['required', 'integer', 'min:0', 'max:1440'],
            'progress_percentage' => ['nullable', 'integer', 'min:0', 'max:100'],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            ...$this->attachmentRules(),
        ], $this->fileMessages());

        DB::transaction(function () use ($request, $ticket, $validated): void {
            $activity = $ticket->activities()->create([
                'user_id' => $request->user()->id,
                'activity_date' => $validated['activity_date'],
                'activity_type' => $validated['activity_type'],
                'minutes_spent' => $validated['minutes_spent'],
                'progress_percentage' => $validated['progress_percentage'] ?? null,
                'description' => $this->clean($validated['description']),
                'diagnosis' => $this->clean($validated['diagnosis'] ?? null),
            ]);
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $request->user(), $request->file('attachments'), 'activity', true, null, $activity);
            }
            $ticket->update([
                'first_response_at' => $ticket->first_response_at ?? now(),
                'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
            ]);
            $this->history->record($ticket, $request->user(), 'activity_logged', 'Actividad técnica documentada.', [], [
                'type' => $activity->type_label, 'minutes' => $activity->minutes_spent,
                'progress' => $activity->progress_percentage,
            ], $request);
        });

        return back()->with('success', 'Actividad técnica registrada.');
    }

    public function relate(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('relate', $ticket);
        $validated = $request->validate([
            'related_ticket_id' => ['required', 'integer', 'different:ticket_id', 'exists:tickets,id'],
            'type' => ['required', Rule::in(array_keys(TicketRelation::TYPES))],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $related = Ticket::findOrFail($validated['related_ticket_id']);
        if ($related->id === $ticket->id) {
            throw ValidationException::withMessages(['related_ticket_id' => 'Un ticket no puede relacionarse consigo mismo.']);
        }
        $this->authorize('view', $related);

        $left = $ticket;
        $right = $related;
        if ($validated['type'] !== 'parent' && $left->id > $right->id) {
            [$left, $right] = [$right, $left];
        }
        $relation = TicketRelation::updateOrCreate([
            'ticket_id' => $left->id,
            'related_ticket_id' => $right->id,
            'type' => $validated['type'],
        ], [
            'created_by_id' => $request->user()->id,
            'note' => $this->clean($validated['note'] ?? null),
        ]);
        $this->history->record($ticket, $request->user(), 'ticket_related', 'Ticket vinculado con '.$related->number.'.', [], [
            'related_ticket' => $related->number, 'type' => $relation->type,
        ], $request);

        return back()->with('success', 'Relación entre tickets registrada.');
    }

    public function solve(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('solve', $ticket);
        if ($ticket->approval_status === 'pending') {
            throw ValidationException::withMessages(['solution' => 'No se puede solucionar un ticket pendiente de aprobación.']);
        }
        $validated = $request->validate([
            'solution' => ['required', 'string', 'min:20', 'max:15000'],
            ...$this->attachmentRules(),
        ], $this->fileMessages());

        DB::transaction(function () use ($request, $ticket, $validated): void {
            $from = ['status' => $ticket->status?->name, 'solution' => $ticket->solution];
            $ticket->update([
                'solution' => $this->clean($validated['solution']),
                'solution_by_id' => $request->user()->id,
                'solved_at' => now(),
                'ticket_status_id' => $this->workflow->status('solucionado')->id,
                'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
            ]);
            if ($request->hasFile('attachments')) {
                $this->attachments->storeMany($ticket, $request->user(), $request->file('attachments'), 'solution');
            }
            $this->history->record($ticket, $request->user(), 'solution_registered', 'Solución documentada y remitida al solicitante.',
                $from, ['status' => 'Solucionado', 'solution' => $ticket->solution], $request);
        });

        return back()->with('success', 'Solución enviada al solicitante para su aceptación.');
    }

    public function review(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('review', $ticket);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'rejected'])],
            'rating' => ['nullable', 'required_if:decision,accepted', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'confirmation' => ['nullable', 'required_if:decision,accepted', 'accepted'],
        ]);
        if ($validated['decision'] === 'rejected' && mb_strlen(trim((string) ($validated['comment'] ?? ''))) < 10) {
            throw ValidationException::withMessages(['comment' => 'Explica por qué la solución no es satisfactoria (mínimo 10 caracteres).']);
        }

        DB::transaction(function () use ($request, $ticket, $validated): void {
            $accepted = $validated['decision'] === 'accepted';
            if ($accepted) {
                $this->inventory->consumeForTicket($ticket, $request->user());
            }
            TicketReview::updateOrCreate(['ticket_id' => $ticket->id], [
                'reviewed_by_id' => $request->user()->id,
                'decision' => $validated['decision'],
                'rating' => $accepted ? $validated['rating'] : null,
                'comment' => $this->clean($validated['comment'] ?? null),
                'confirmed_at' => now(),
                'confirmation_ip' => $request->ip(),
            ]);
            $ticket->update([
                'ticket_status_id' => $this->workflow->status($accepted ? 'cerrado' : 'en-proceso')->id,
                'closed_by_id' => $accepted ? $request->user()->id : null,
                'closed_at' => $accepted ? now() : null,
                'last_activity_at' => now(), 'lock_version' => $ticket->lock_version + 1,
            ]);
            $this->history->record($ticket, $request->user(), $accepted ? 'solution_accepted' : 'solution_rejected',
                $accepted ? 'Solución aceptada mediante confirmación electrónica; ticket cerrado.' : 'Solución rechazada; ticket devuelto a proceso.',
                ['status' => 'Solucionado'], [
                    'status' => $accepted ? 'Cerrado' : 'En proceso',
                    'rating' => $accepted ? $validated['rating'] : null,
                    'comment' => $validated['comment'] ?? null,
                ], $request);
        });

        return back()->with('success', $validated['decision'] === 'accepted'
            ? 'Servicio aceptado y ticket cerrado.' : 'La solución fue devuelta al equipo de IT.');
    }

    private function messageRules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:10000'], ...$this->attachmentRules()];
    }

    private function attachmentRules(): array
    {
        return [
            'attachments' => ['nullable', 'array', 'max:'.UploadSecurityPolicy::MAX_FILES_PER_REQUEST],
            'attachments.*' => UploadSecurityPolicy::fileRules(TicketAttachmentService::ALLOWED_EXTENSIONS),
        ];
    }

    private function fileMessages(): array
    {
        return ['attachments.*' => 'Uno de los archivos no es válido, supera 5 MB o tiene un tipo no permitido.'];
    }

    private function clean(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }
}

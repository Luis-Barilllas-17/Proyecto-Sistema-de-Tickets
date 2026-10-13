<?php

namespace App\Services;

use App\Models\DevelopmentTestEvidence;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class TicketAttachmentService
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv', 'zip'];

    public function storeMany(
        Ticket $ticket,
        User $user,
        array $files,
        string $context,
        bool $isInternal = false,
        ?TicketComment $comment = null,
        ?TicketActivity $activity = null,
        ?DevelopmentTestEvidence $testEvidence = null,
    ): Collection {
        if (! in_array($context, ['initial', 'comment', 'activity', 'solution', 'development_request', 'development_test'], true)) {
            throw new RuntimeException('Contexto de archivo no permitido.');
        }

        $created = collect();
        $storedPaths = [];

        try {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile || ! $file->isValid()) {
                    throw new RuntimeException('El archivo recibido no es válido.');
                }

                $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
                if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
                    throw new RuntimeException('El tipo de archivo no está permitido.');
                }

                $storedName = Str::uuid()->toString().'.'.$extension;
                $path = $file->storeAs('ticket-attachments/'.$ticket->id, $storedName, 'local');
                if (! $path) {
                    throw new RuntimeException('No fue posible almacenar el archivo.');
                }
                $storedPaths[] = $path;

                $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $file->getClientOriginalName())));
                $created->push(TicketAttachment::create([
                    'ticket_id' => $ticket->id,
                    'uploaded_by_id' => $user->id,
                    'ticket_comment_id' => $comment?->id,
                    'ticket_activity_id' => $activity?->id,
                    'development_test_evidence_id' => $testEvidence?->id,
                    'context' => $context,
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => mb_substr($originalName ?: 'archivo.'.$extension, 0, 255),
                    'mime_type' => mb_substr((string) $file->getMimeType(), 0, 120),
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                    'is_internal' => $isInternal,
                ]));
            }
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            $created->each->delete();
            throw $exception;
        }

        return $created;
    }
}

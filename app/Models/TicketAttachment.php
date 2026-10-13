<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    protected $fillable = [
        'ticket_id', 'uploaded_by_id', 'ticket_comment_id', 'ticket_activity_id', 'development_test_evidence_id',
        'context', 'disk', 'path', 'original_name', 'mime_type', 'size', 'sha256', 'is_internal',
    ];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(TicketComment::class, 'ticket_comment_id');
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(TicketActivity::class, 'ticket_activity_id');
    }

    public function developmentTestEvidence(): BelongsTo
    {
        return $this->belongsTo(DevelopmentTestEvidence::class);
    }

    public function getHumanSizeAttribute(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format(max(1, $this->size / 1024), 0).' KB';
    }
}

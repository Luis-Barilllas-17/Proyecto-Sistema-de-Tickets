<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketRelation extends Model
{
    public const TYPES = [
        'related' => 'Relacionado',
        'duplicate' => 'Duplicado',
        'parent' => 'Caso principal',
    ];

    protected $fillable = ['ticket_id', 'related_ticket_id', 'type', 'created_by_id', 'note'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function relatedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'related_ticket_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? str($this->type)->headline()->toString();
    }
}

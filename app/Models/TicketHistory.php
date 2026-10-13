<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id', 'user_id', 'event', 'description', 'from_values', 'to_values',
        'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return ['from_values' => 'array', 'to_values' => 'array', 'created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

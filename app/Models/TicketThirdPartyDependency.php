<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketThirdPartyDependency extends Model
{
    protected $fillable = [
        'ticket_id', 'provider', 'reference', 'description', 'status', 'requested_at',
        'due_at', 'resolved_at', 'resolution', 'created_by_id', 'resolved_by_id',
    ];

    protected function casts(): array
    {
        return ['requested_at' => 'datetime', 'due_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}

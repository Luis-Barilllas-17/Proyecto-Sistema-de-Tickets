<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketSupplyUsage extends Model
{
    public const STATUSES = [
        'reserved' => 'Reservado',
        'consumed' => 'Consumido',
        'released' => 'Liberado',
    ];

    protected $fillable = [
        'ticket_id', 'supply_id', 'quantity_reserved', 'quantity_consumed', 'status', 'notes',
        'recorded_by_id', 'consumed_by_id', 'consumed_at', 'released_by_id', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_reserved' => 'decimal:2', 'quantity_consumed' => 'decimal:2',
            'consumed_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}

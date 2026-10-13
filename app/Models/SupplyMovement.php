<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyMovement extends Model
{
    public const TYPES = [
        'entry' => 'Entrada',
        'exit' => 'Salida',
        'adjustment' => 'Ajuste',
        'consumption' => 'Consumo por ticket',
    ];

    protected $fillable = [
        'supply_id', 'ticket_id', 'ticket_supply_usage_id', 'movement_type', 'quantity_delta',
        'balance_before', 'balance_after', 'justification', 'responsible_id', 'authorized_by_id',
    ];

    protected function casts(): array
    {
        return ['quantity_delta' => 'decimal:2', 'balance_before' => 'decimal:2', 'balance_after' => 'decimal:2'];
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function ticketUsage(): BelongsTo
    {
        return $this->belongsTo(TicketSupplyUsage::class, 'ticket_supply_usage_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->movement_type] ?? str($this->movement_type)->headline()->toString();
    }
}

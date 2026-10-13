<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentEvent extends Model
{
    public const TYPES = [
        'maintenance' => 'Mantenimiento',
        'repair' => 'Reparación',
        'inspection' => 'Inspección',
        'configuration' => 'Configuración',
        'change' => 'Cambio técnico',
        'note' => 'Nota de inventario',
    ];

    protected $fillable = [
        'equipment_id', 'ticket_id', 'event_type', 'title', 'description', 'performed_at',
        'provider', 'cost', 'downtime_minutes', 'metadata', 'created_by_id',
    ];

    protected function casts(): array
    {
        return ['performed_at' => 'datetime', 'cost' => 'decimal:2', 'metadata' => 'array'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->event_type] ?? str($this->event_type)->headline()->toString();
    }
}

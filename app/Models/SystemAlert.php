<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemAlert extends Model
{
    public const SEVERITIES = ['info' => 'Informativa', 'warning' => 'Próxima a vencer', 'critical' => 'Vencida'];

    protected $fillable = [
        'fingerprint', 'ticket_id', 'maintenance_order_id', 'type', 'severity', 'title',
        'message', 'status', 'due_at', 'detected_at', 'resolved_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime', 'detected_at' => 'datetime', 'resolved_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function maintenanceOrder(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class);
    }
}

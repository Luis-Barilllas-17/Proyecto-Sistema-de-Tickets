<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceOrderItem extends Model
{
    public const STATUSES = [
        'pending' => 'Pendiente',
        'completed' => 'Realizado',
        'not_applicable' => 'No aplica',
    ];

    protected $fillable = [
        'maintenance_order_id', 'maintenance_checklist_item_id', 'label', 'sort_order',
        'is_required', 'status', 'observation', 'completed_by_id', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'completed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }
}

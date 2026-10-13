<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlanEquipment extends Model
{
    protected $table = 'maintenance_plan_equipment';

    protected $fillable = [
        'maintenance_plan_id', 'equipment_id', 'start_date', 'next_due_date',
        'last_completed_on', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date', 'next_due_date' => 'date', 'last_completed_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class);
    }
}

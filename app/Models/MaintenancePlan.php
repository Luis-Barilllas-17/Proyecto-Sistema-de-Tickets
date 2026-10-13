<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenancePlan extends Model
{
    public const FREQUENCIES = [
        1 => 'Mensual',
        3 => 'Trimestral',
        6 => 'Semestral',
        12 => 'Anual',
        24 => 'Cada dos años',
    ];

    protected $fillable = [
        'code', 'name', 'description', 'frequency_months', 'lead_days', 'duration_minutes',
        'default_start_time', 'assigned_it_area_id', 'assigned_it_group_id', 'assigned_user_id',
        'is_active', 'created_by_id', 'updated_by_id',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'assigned_it_area_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TechnicalGroup::class, 'assigned_it_group_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MaintenancePlanEquipment::class)->orderBy('next_due_date');
    }

    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class, 'maintenance_plan_equipment')
            ->withPivot(['id', 'start_date', 'next_due_date', 'last_completed_on', 'notes', 'is_active'])
            ->withTimestamps();
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(MaintenanceChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class)->latest('scheduled_for');
    }

    public function getFrequencyLabelAttribute(): string
    {
        return self::FREQUENCIES[$this->frequency_months] ?? 'Cada '.$this->frequency_months.' meses';
    }
}

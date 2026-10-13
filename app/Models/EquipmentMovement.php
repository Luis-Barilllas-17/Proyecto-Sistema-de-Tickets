<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentMovement extends Model
{
    public const TYPES = [
        'assignment' => 'Asignación',
        'return' => 'Devolución',
        'transfer' => 'Traslado',
        'status_change' => 'Cambio de estado',
        'retirement' => 'Baja',
    ];

    protected $fillable = [
        'equipment_id', 'movement_type', 'from_area_id', 'to_area_id', 'from_department_id',
        'to_department_id', 'from_responsible_id', 'to_responsible_id', 'from_location',
        'to_location', 'from_status', 'to_status', 'effective_at', 'justification',
        'authorized_by_id', 'created_by_id',
    ];

    protected function casts(): array
    {
        return ['effective_at' => 'datetime'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function fromArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'from_area_id');
    }

    public function toArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'to_area_id');
    }

    public function fromDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function fromResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_responsible_id');
    }

    public function toResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_responsible_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->movement_type] ?? str($this->movement_type)->headline()->toString();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaintenanceOrder extends Model
{
    public const STATUSES = [
        'scheduled' => 'Programado',
        'work_order_generated' => 'Orden generada',
        'in_progress' => 'En ejecución',
        'completed' => 'Completado',
        'overdue' => 'Vencido',
        'cancelled' => 'Cancelado',
    ];

    public const RESULTS = [
        'operational' => 'Equipo operativo',
        'follow_up' => 'Requiere seguimiento',
        'repair_required' => 'Requiere reparación',
    ];

    protected $fillable = [
        'number', 'maintenance_plan_id', 'maintenance_plan_equipment_id', 'equipment_id', 'ticket_id',
        'scheduled_for', 'scheduled_start_at', 'scheduled_end_at', 'status', 'assigned_to_id',
        'generation_source', 'generated_at', 'started_at', 'completed_at', 'result', 'observations',
        'equipment_previous_status', 'created_by_id', 'completed_by_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date', 'scheduled_start_at' => 'datetime', 'scheduled_end_at' => 'datetime',
            'generated_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class, 'maintenance_plan_id');
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlanEquipment::class, 'maintenance_plan_equipment_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(MaintenanceEvidence::class)->oldest();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function getResultLabelAttribute(): ?string
    {
        return $this->result ? (self::RESULTS[$this->result] ?? str($this->result)->headline()->toString()) : null;
    }
}

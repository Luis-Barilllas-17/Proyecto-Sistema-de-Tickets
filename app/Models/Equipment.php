<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Equipment extends Model
{
    protected $table = 'equipment';

    public const TYPES = [
        'computer' => 'Computadora',
        'server' => 'Servidor',
        'printer' => 'Impresora',
        'network_device' => 'Dispositivo de red',
        'phone' => 'Teléfono',
        'peripheral' => 'Periférico',
        'other' => 'Otro activo',
    ];

    public const STATUSES = [
        'available' => 'Disponible',
        'assigned' => 'Asignado',
        'maintenance' => 'En mantenimiento',
        'repair' => 'En reparación',
        'stored' => 'En resguardo',
        'retired' => 'Dado de baja',
    ];

    protected $fillable = [
        'inventory_code', 'asset_type', 'name', 'brand', 'model', 'serial_number',
        'ip_address', 'mac_address', 'hostname', 'network_segment', 'connectivity_notes',
        'processor', 'memory_mb', 'storage_gb', 'operating_system', 'purchase_date',
        'warranty_expires_at', 'acquisition_cost', 'additional_specifications', 'area_id',
        'department_id', 'responsible_user_id', 'location', 'status', 'condition_notes',
        'is_active', 'created_by_id', 'updated_by_id',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_expires_at' => 'date',
            'storage_gb' => 'decimal:2',
            'acquisition_cost' => 'decimal:2',
            'additional_specifications' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(EquipmentLicense::class)->orderBy('name');
    }

    public function events(): HasMany
    {
        return $this->hasMany(EquipmentEvent::class)->latest('performed_at')->latest('id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EquipmentMovement::class)->latest('effective_at')->latest('id');
    }

    public function ticketLinks(): HasMany
    {
        return $this->hasMany(TicketEquipmentLink::class);
    }

    public function maintenanceAssignments(): HasMany
    {
        return $this->hasMany(MaintenancePlanEquipment::class);
    }

    public function maintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class)->latest('scheduled_for');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->asset_type] ?? str($this->asset_type)->headline()->toString();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }

    public function getMemoryLabelAttribute(): ?string
    {
        if (! $this->memory_mb) {
            return null;
        }

        return $this->memory_mb >= 1024
            ? rtrim(rtrim(number_format($this->memory_mb / 1024, 2), '0'), '.').' GB'
            : $this->memory_mb.' MB';
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supply extends Model
{
    public const UNITS = [
        'unit' => 'Unidad',
        'meter' => 'Metro',
        'box' => 'Caja',
        'package' => 'Paquete',
        'liter' => 'Litro',
    ];

    protected $fillable = [
        'code', 'name', 'category', 'unit', 'stock_quantity', 'minimum_quantity',
        'storage_location', 'description', 'is_active', 'created_by_id', 'updated_by_id',
    ];

    protected function casts(): array
    {
        return ['stock_quantity' => 'decimal:2', 'minimum_quantity' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(SupplyMovement::class)->latest();
    }

    public function ticketUsages(): HasMany
    {
        return $this->hasMany(TicketSupplyUsage::class)->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function getUnitLabelAttribute(): string
    {
        return self::UNITS[$this->unit] ?? str($this->unit)->headline()->toString();
    }

    public function getIsLowStockAttribute(): bool
    {
        return (float) $this->stock_quantity <= (float) $this->minimum_quantity;
    }

    public function reservedQuantity(): float
    {
        return (float) $this->ticketUsages()->where('status', 'reserved')->sum('quantity_reserved');
    }

    public function availableQuantity(): float
    {
        return max(0, (float) $this->stock_quantity - $this->reservedQuantity());
    }
}

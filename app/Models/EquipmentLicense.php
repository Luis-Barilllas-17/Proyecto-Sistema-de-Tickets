<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentLicense extends Model
{
    protected $fillable = ['equipment_id', 'name', 'license_reference', 'seats', 'expires_at', 'notes', 'created_by_id'];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}

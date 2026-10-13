<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketPriority extends Model
{
    protected $fillable = ['code', 'name', 'color', 'weight', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function serviceLevelTargets(): HasMany
    {
        return $this->hasMany(ServiceLevelTarget::class);
    }
}

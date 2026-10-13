<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpactLevel extends Model
{
    protected $fillable = ['name', 'value', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

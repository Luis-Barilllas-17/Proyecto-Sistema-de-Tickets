<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketStatus extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'is_initial', 'is_final', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_initial' => 'boolean', 'is_final' => 'boolean', 'is_active' => 'boolean'];
    }
}

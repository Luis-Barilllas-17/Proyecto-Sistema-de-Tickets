<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'ticket_category_id', 'ticket_subcategory_id', 'requester_area_id', 'minimum_priority_id',
        'code', 'name', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(TicketSubcategory::class, 'ticket_subcategory_id');
    }

    public function requesterArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'requester_area_id');
    }

    public function minimumPriority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'minimum_priority_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(ApprovalLevel::class)->orderBy('level_number');
    }
}

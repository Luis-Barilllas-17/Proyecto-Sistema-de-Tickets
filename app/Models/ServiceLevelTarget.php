<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceLevelTarget extends Model
{
    protected $fillable = [
        'ticket_priority_id', 'ticket_category_id', 'ticket_subcategory_id', 'response_minutes',
        'resolution_minutes', 'escalation_minutes', 'business_hours_only', 'is_active',
    ];

    protected function casts(): array
    {
        return ['business_hours_only' => 'boolean', 'is_active' => 'boolean'];
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(TicketSubcategory::class, 'ticket_subcategory_id');
    }
}

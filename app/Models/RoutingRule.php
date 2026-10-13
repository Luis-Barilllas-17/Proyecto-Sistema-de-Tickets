<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingRule extends Model
{
    protected $fillable = [
        'ticket_category_id', 'ticket_subcategory_id', 'it_area_id', 'it_group_id', 'assignee_user_id',
        'name', 'precedence', 'is_active',
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

    public function itArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'it_area_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TechnicalGroup::class, 'it_group_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriorityMatrixRule extends Model
{
    protected $fillable = ['impact_level_id', 'urgency_level_id', 'ticket_priority_id'];

    public function impact(): BelongsTo
    {
        return $this->belongsTo(ImpactLevel::class, 'impact_level_id');
    }

    public function urgency(): BelongsTo
    {
        return $this->belongsTo(UrgencyLevel::class, 'urgency_level_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEscalation extends Model
{
    public const LEVELS = [
        'area_manager' => 'Encargado del área de IT',
        'it_head' => 'Jefatura de IT',
    ];

    protected $fillable = [
        'ticket_id', 'level', 'source', 'trigger_key', 'reason', 'escalated_to_user_id',
        'created_by_id', 'escalated_at', 'acknowledged_by_id', 'acknowledged_at',
    ];

    protected function casts(): array
    {
        return ['escalated_at' => 'datetime', 'acknowledged_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? str($this->level)->headline()->toString();
    }
}

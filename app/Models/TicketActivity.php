<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketActivity extends Model
{
    public const TYPES = [
        'analysis' => 'Análisis',
        'diagnosis' => 'Diagnóstico',
        'work' => 'Trabajo realizado',
        'testing' => 'Pruebas',
        'coordination' => 'Coordinación',
        'documentation' => 'Documentación',
    ];

    protected $fillable = [
        'ticket_id', 'user_id', 'activity_date', 'activity_type', 'minutes_spent',
        'description', 'diagnosis', 'progress_percentage',
    ];

    protected function casts(): array
    {
        return ['activity_date' => 'date'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->activity_type] ?? str($this->activity_type)->headline()->toString();
    }
}

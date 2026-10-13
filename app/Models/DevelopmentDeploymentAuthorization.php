<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentDeploymentAuthorization extends Model
{
    public const STATUSES = [
        'pending' => 'Pendiente de autorización',
        'authorized' => 'Autorizado',
        'rejected' => 'Rechazado',
        'cancelled' => 'Cancelado',
    ];

    protected $fillable = [
        'development_request_id', 'version', 'environment', 'release_notes', 'deployment_plan',
        'rollback_plan', 'scheduled_at', 'requested_by_id', 'requested_at', 'status',
        'decided_by_id', 'decided_at', 'decision_comment',
    ];

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime', 'requested_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function developmentRequest(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRequest::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}

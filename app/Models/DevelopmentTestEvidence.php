<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentTestEvidence extends Model
{
    protected $table = 'development_test_evidences';

    public const TYPES = [
        'functional' => 'Prueba funcional',
        'technical' => 'Prueba técnica',
        'security' => 'Prueba de seguridad',
        'user_acceptance' => 'Aceptación del usuario',
    ];

    public const RESULTS = ['passed' => 'Aprobada', 'failed' => 'Fallida', 'blocked' => 'Bloqueada'];

    protected $fillable = [
        'development_request_id', 'test_type', 'title', 'environment', 'result',
        'executed_at', 'executed_by_id', 'notes',
    ];

    protected function casts(): array
    {
        return ['executed_at' => 'datetime'];
    }

    public function developmentRequest(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRequest::class);
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->test_type] ?? str($this->test_type)->headline()->toString();
    }

    public function getResultLabelAttribute(): string
    {
        return self::RESULTS[$this->result] ?? str($this->result)->headline()->toString();
    }
}

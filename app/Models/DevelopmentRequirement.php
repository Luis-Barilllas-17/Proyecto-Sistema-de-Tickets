<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentRequirement extends Model
{
    public const TYPES = [
        'functional' => 'Requisito funcional',
        'user_story' => 'Historia de usuario',
        'acceptance_criterion' => 'Criterio de aceptación',
        'technical_restriction' => 'Restricción técnica',
        'security' => 'Requisito de seguridad',
    ];

    public const PRIORITIES = ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta', 'critical' => 'Crítica'];

    public const STATUSES = ['draft' => 'Borrador', 'approved' => 'Aprobado', 'implemented' => 'Implementado', 'verified' => 'Verificado'];

    protected $fillable = [
        'development_request_id', 'code', 'type', 'title', 'description', 'acceptance_criteria',
        'technical_restrictions', 'priority', 'status', 'sort_order', 'created_by_id', 'updated_by_id',
    ];

    public function developmentRequest(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? str($this->type)->headline()->toString();
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? str($this->priority)->headline()->toString();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}

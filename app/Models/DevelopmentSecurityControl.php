<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentSecurityControl extends Model
{
    public const TYPES = [
        'authentication' => 'Autenticación',
        'authorization' => 'Autorización',
        'encryption' => 'Cifrado',
        'activity_logging' => 'Registros de actividad',
        'input_validation' => 'Validación de entradas',
        'error_handling' => 'Manejo seguro de errores',
    ];

    public const STATUSES = [
        'planned' => 'Planificado',
        'implemented' => 'Implementado',
        'verified' => 'Verificado',
        'not_applicable' => 'No aplica',
    ];

    protected $fillable = [
        'development_request_id', 'control_type', 'is_required', 'requirement_details',
        'implementation_notes', 'status', 'verified_by_id', 'verified_at',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'verified_at' => 'datetime'];
    }

    public function developmentRequest(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRequest::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->control_type] ?? str($this->control_type)->headline()->toString();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}

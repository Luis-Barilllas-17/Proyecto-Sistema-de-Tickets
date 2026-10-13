<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevelopmentRequest extends Model
{
    public const REQUEST_TYPES = [
        'new_system' => 'Nuevo sistema',
        'new_module' => 'Nuevo módulo',
        'improvement' => 'Mejora funcional',
        'correction' => 'Corrección',
        'automation' => 'Automatización',
    ];

    public const INFORMATION_CLASSIFICATIONS = [
        'public' => 'Pública',
        'internal' => 'Uso interno',
        'confidential' => 'Confidencial',
        'restricted' => 'Restringida',
    ];

    public const LIFECYCLE_STATUSES = [
        'analysis' => 'Análisis',
        'definition' => 'Definición',
        'development' => 'Desarrollo',
        'testing' => 'Pruebas',
        'deployment_pending' => 'Despliegue pendiente',
        'deployment_rejected' => 'Despliegue rechazado',
        'closed' => 'Cerrado',
    ];

    protected $fillable = [
        'ticket_id', 'request_type', 'project_name', 'business_need', 'objective', 'scope',
        'expected_benefits', 'affected_system', 'expected_delivery_date', 'technical_restrictions',
        'information_classification', 'contains_sensitive_data', 'sensitive_data_description',
        'involves_third_parties', 'third_party_details', 'access_overview', 'repository_reference',
        'lifecycle_status',
    ];

    protected function casts(): array
    {
        return [
            'expected_delivery_date' => 'date',
            'contains_sensitive_data' => 'boolean',
            'involves_third_parties' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(DevelopmentRequirement::class)->orderBy('sort_order')->orderBy('id');
    }

    public function securityControls(): HasMany
    {
        return $this->hasMany(DevelopmentSecurityControl::class)->orderBy('id');
    }

    public function accessProfiles(): HasMany
    {
        return $this->hasMany(DevelopmentAccessProfile::class)->orderBy('name');
    }

    public function testEvidences(): HasMany
    {
        return $this->hasMany(DevelopmentTestEvidence::class)->latest('executed_at');
    }

    public function deploymentAuthorizations(): HasMany
    {
        return $this->hasMany(DevelopmentDeploymentAuthorization::class)->latest();
    }

    public function getRequestTypeLabelAttribute(): string
    {
        return self::REQUEST_TYPES[$this->request_type] ?? str($this->request_type)->headline()->toString();
    }

    public function getInformationClassificationLabelAttribute(): string
    {
        return self::INFORMATION_CLASSIFICATIONS[$this->information_classification] ?? str($this->information_classification)->headline()->toString();
    }

    public function getLifecycleStatusLabelAttribute(): string
    {
        return self::LIFECYCLE_STATUSES[$this->lifecycle_status] ?? str($this->lifecycle_status)->headline()->toString();
    }
}

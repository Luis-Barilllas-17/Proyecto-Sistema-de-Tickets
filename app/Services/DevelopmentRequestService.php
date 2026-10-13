<?php

namespace App\Services;

use App\Models\DevelopmentRequest;
use App\Models\DevelopmentSecurityControl;
use App\Models\DevelopmentTestEvidence;
use Illuminate\Support\Collection;

class DevelopmentRequestService
{
    public function initializeSecurityControls(DevelopmentRequest $developmentRequest, array $requiredControls = []): void
    {
        foreach (DevelopmentSecurityControl::TYPES as $type => $label) {
            $developmentRequest->securityControls()->firstOrCreate(['control_type' => $type], [
                'is_required' => in_array($type, $requiredControls, true),
                'requirement_details' => in_array($type, $requiredControls, true)
                    ? 'Control identificado durante el registro inicial de la solicitud.' : null,
                'status' => in_array($type, $requiredControls, true) ? 'planned' : 'not_applicable',
            ]);
        }
    }

    public function deploymentReadiness(DevelopmentRequest $developmentRequest, bool $ignorePendingDeployment = false): Collection
    {
        $developmentRequest->loadMissing(['requirements', 'securityControls', 'testEvidences.attachments', 'deploymentAuthorizations']);
        $issues = collect();

        if ($developmentRequest->requirements->isEmpty()) {
            $issues->push('Registra al menos un requisito funcional, historia de usuario o restricción técnica.');
        }
        if ($developmentRequest->requirements->contains(fn ($requirement) => $requirement->status === 'draft')) {
            $issues->push('Todos los requerimientos deben estar aprobados, implementados o verificados.');
        }
        if ($developmentRequest->securityControls->where('is_required', true)
            ->contains(fn ($control) => $control->status !== 'verified')) {
            $issues->push('Todos los controles de seguridad requeridos deben estar verificados por un responsable autorizado.');
        }

        foreach (array_keys(DevelopmentTestEvidence::TYPES) as $type) {
            $validEvidence = $developmentRequest->testEvidences
                ->where('test_type', $type)->where('result', 'passed')
                ->contains(fn ($evidence) => $evidence->attachments->isNotEmpty());
            if (! $validEvidence) {
                $issues->push('Falta una prueba aprobada con evidencia para «'.DevelopmentTestEvidence::TYPES[$type].'».');
            }
        }

        if (! $ignorePendingDeployment && $developmentRequest->deploymentAuthorizations->contains('status', 'pending')) {
            $issues->push('Ya existe una autorización de despliegue pendiente.');
        }

        return $issues;
    }

    public function clean(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value));
    }
}

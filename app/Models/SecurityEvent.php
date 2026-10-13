<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email',
        'event',
        'ip_address',
        'user_agent',
        'details',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $event,
        Request $request,
        ?User $user = null,
        array $details = [],
        ?string $email = null,
    ): self {
        return self::create([
            'user_id' => $user?->id,
            'email' => $email ?? $user?->email,
            'event' => $event,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
            'details' => $details ?: null,
        ]);
    }

    public function getLabelAttribute(): string
    {
        return match ($this->event) {
            'login_success' => 'Inicio de sesión exitoso',
            'login_failed' => 'Intento de acceso fallido',
            'login_blocked' => 'Acceso bloqueado',
            'account_locked' => 'Cuenta bloqueada temporalmente',
            'account_unlocked' => 'Cuenta desbloqueada',
            'logout' => 'Cierre de sesión',
            'logout_inactivity' => 'Cierre automático por inactividad',
            'password_reset_requested' => 'Recuperación solicitada',
            'password_reset_completed' => 'Contraseña restablecida',
            'password_changed' => 'Contraseña actualizada',
            'password_admin_reset' => 'Restablecimiento administrativo',
            'account_activated' => 'Cuenta activada',
            'account_suspended' => 'Cuenta suspendida',
            'account_inactive' => 'Cuenta desactivada',
            'session_revoked' => 'Sesión finalizada',
            'user_created' => 'Usuario creado',
            'user_updated' => 'Usuario actualizado',
            'role_created' => 'Rol creado',
            'role_updated' => 'Rol y permisos actualizados',
            'area_created' => 'Área creada',
            'area_updated' => 'Área actualizada',
            'department_created' => 'Departamento creado',
            'department_updated' => 'Departamento actualizado',
            'group_created' => 'Grupo técnico creado',
            'group_updated' => 'Grupo técnico actualizado',
            'specialty_created' => 'Especialidad creada',
            'specialty_updated' => 'Especialidad actualizada',
            'category_created' => 'Categoría de tickets creada',
            'category_updated' => 'Categoría de tickets actualizada',
            'subcategory_created' => 'Subcategoría creada',
            'subcategory_updated' => 'Subcategoría actualizada',
            'form_created' => 'Formulario creado',
            'form_updated' => 'Formulario actualizado',
            'form_field_created' => 'Campo de formulario creado',
            'form_field_updated' => 'Campo de formulario actualizado',
            'approval_workflow_created' => 'Regla de aprobación creada',
            'approval_workflow_updated' => 'Regla de aprobación actualizada',
            'approval_level_created' => 'Nivel de aprobación creado',
            'approval_level_updated' => 'Nivel de aprobación actualizado',
            'approval_delegation_created' => 'Suplencia de aprobación creada',
            'approval_delegation_updated' => 'Suplencia de aprobación actualizada',
            'routing_rule_created' => 'Regla de direccionamiento creada',
            'routing_rule_updated' => 'Regla de direccionamiento actualizada',
            'ticket_status_created' => 'Estado de ticket creado',
            'ticket_status_updated' => 'Estado de ticket actualizado',
            'impact_level_created' => 'Nivel de impacto creado',
            'impact_level_updated' => 'Nivel de impacto actualizado',
            'urgency_level_created' => 'Nivel de urgencia creado',
            'urgency_level_updated' => 'Nivel de urgencia actualizado',
            'ticket_priority_created' => 'Prioridad creada',
            'ticket_priority_updated' => 'Prioridad actualizada',
            'priority_matrix_updated' => 'Matriz de prioridad actualizada',
            'sla_target_created' => 'Objetivo SLA creado',
            'sla_target_updated' => 'Objetivo SLA actualizado',
            default => str($this->event)->replace('_', ' ')->title()->toString(),
        };
    }

    public function getToneAttribute(): string
    {
        return match ($this->event) {
            'login_success', 'account_activated', 'account_unlocked' => 'success',
            'login_failed', 'login_blocked', 'account_locked', 'account_suspended', 'account_inactive' => 'warning',
            'password_reset_completed', 'password_changed', 'password_admin_reset',
            'user_created', 'user_updated', 'role_created', 'role_updated',
            'area_created', 'area_updated', 'department_created', 'department_updated',
            'group_created', 'group_updated', 'specialty_created', 'specialty_updated',
            'category_created', 'category_updated', 'subcategory_created', 'subcategory_updated',
            'form_created', 'form_updated', 'form_field_created', 'form_field_updated',
            'approval_workflow_created', 'approval_workflow_updated', 'approval_level_created', 'approval_level_updated',
            'approval_delegation_created', 'approval_delegation_updated', 'routing_rule_created', 'routing_rule_updated',
            'ticket_status_created', 'ticket_status_updated', 'impact_level_created', 'impact_level_updated',
            'urgency_level_created', 'urgency_level_updated', 'ticket_priority_created', 'ticket_priority_updated',
            'priority_matrix_updated', 'sla_target_created', 'sla_target_updated' => 'info',
            default => 'neutral',
        };
    }
}

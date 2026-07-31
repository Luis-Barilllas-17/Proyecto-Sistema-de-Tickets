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
            default => str($this->event)->replace('_', ' ')->title()->toString(),
        };
    }

    public function getToneAttribute(): string
    {
        return match ($this->event) {
            'login_success', 'account_activated', 'account_unlocked' => 'success',
            'login_failed', 'login_blocked', 'account_locked', 'account_suspended', 'account_inactive' => 'warning',
            'password_reset_completed', 'password_changed', 'password_admin_reset' => 'info',
            default => 'neutral',
        };
    }
}

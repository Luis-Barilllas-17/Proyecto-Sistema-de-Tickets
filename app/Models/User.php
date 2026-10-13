<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'employee_code',
        'email',
        'job_title',
        'phone_extension',
        'area_id',
        'department_id',
        'email_verified_at',
        'password',
        'status',
        'is_super_admin',
        'failed_login_attempts',
        'locked_until',
        'last_failed_login_at',
        'last_login_at',
        'last_login_ip',
        'password_changed_at',
        'force_password_change',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'locked_until' => 'datetime',
            'last_failed_login_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'force_password_change' => 'boolean',
        ];
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function technicalGroups(): BelongsToMany
    {
        return $this->belongsToMany(TechnicalGroup::class, 'group_user', 'user_id', 'it_group_id')
            ->withPivot('membership_role')
            ->withTimestamps();
    }

    public function specialties(): BelongsToMany
    {
        return $this->belongsToMany(Specialty::class)
            ->withPivot(['proficiency_level', 'is_primary'])
            ->withTimestamps();
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'current_assignee_id');
    }

    public function responsibleEquipment(): HasMany
    {
        return $this->hasMany(Equipment::class, 'responsible_user_id');
    }

    public function assignedMaintenanceOrders(): HasMany
    {
        return $this->hasMany(MaintenanceOrder::class, 'assigned_to_id');
    }

    public function ticketActivities(): HasMany
    {
        return $this->hasMany(TicketActivity::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->is_super_admin || $this->hasRole('super-administrador');
    }

    public function hasRole(string $slug): bool
    {
        if ($this->relationLoaded('roles')) {
            return $this->roles->contains(fn (Role $role) => $role->slug === $slug && $role->is_active);
        }

        return $this->roles()->where('slug', $slug)->where('is_active', true)->exists();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        $this->loadMissing('roles.permissions');

        return $this->roles
            ->where('is_active', true)
            ->contains(fn (Role $role) => $role->permissions->contains('slug', $slug));
    }

    public function getPrimaryRoleNameAttribute(): string
    {
        $role = $this->relationLoaded('roles') ? $this->roles->first() : $this->roles()->first();

        return $role?->name ?? ($this->is_super_admin ? 'Super Administrador' : 'Sin perfil asignado');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}

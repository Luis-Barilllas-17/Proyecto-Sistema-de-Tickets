<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalLevel extends Model
{
    public const APPROVER_TYPES = [
        'requester_area_head' => 'Jefatura del área solicitante',
        'it_area_head' => 'Jefatura del área de IT',
        'specific_user' => 'Usuario específico',
        'role' => 'Perfil institucional',
    ];

    protected $fillable = [
        'approval_workflow_id', 'level_number', 'name', 'approver_type', 'approver_user_id',
        'approver_role_id', 'is_required', 'allow_delegation', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean', 'allow_delegation' => 'boolean', 'is_active' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function getApproverTypeLabelAttribute(): string
    {
        return self::APPROVER_TYPES[$this->approver_type] ?? str($this->approver_type)->headline()->toString();
    }
}

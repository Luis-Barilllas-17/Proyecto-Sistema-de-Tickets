<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketApproval extends Model
{
    public const STATUSES = [
        'waiting' => 'En espera',
        'pending' => 'Pendiente',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'changes_requested' => 'Devuelto para corrección',
        'skipped' => 'Omitido',
    ];

    protected $fillable = [
        'ticket_id', 'approval_workflow_id', 'approval_level_id', 'level_number', 'level_name',
        'approver_type', 'approver_user_id', 'approver_role_id', 'status', 'decided_by_id',
        'delegated_for_id', 'comment', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(ApprovalLevel::class, 'approval_level_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    public function delegatedFor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_for_id');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? str($this->status)->headline()->toString();
    }
}

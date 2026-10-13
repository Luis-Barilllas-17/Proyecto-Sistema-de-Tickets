<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ticket extends Model
{
    public const SOURCES = [
        'requester' => 'Usuario solicitante',
        'it_internal' => 'Registro interno de IT',
    ];

    public const ATTENTION_TYPES = [
        'remote' => 'Atención remota',
        'onsite' => 'Atención en sitio',
        'analysis' => 'Análisis técnico',
        'development' => 'Desarrollo',
    ];

    public const APPROVAL_STATUSES = [
        'not_required' => 'No requiere aprobación',
        'pending' => 'Pendiente de aprobación',
        'approved' => 'Aprobado',
        'rejected' => 'Rechazado',
        'changes_requested' => 'Devuelto para corrección',
    ];

    protected $fillable = [
        'number', 'requester_id', 'created_by_id', 'source', 'is_draft', 'title', 'description',
        'ticket_category_id', 'ticket_subcategory_id', 'ticket_form_id', 'requester_area_id',
        'requester_department_id', 'location', 'attention_type', 'impact_level_id', 'urgency_level_id',
        'ticket_priority_id', 'ticket_status_id', 'approval_status', 'current_it_area_id',
        'current_it_group_id', 'current_assignee_id', 'assignment_status', 'submitted_at',
        'response_due_at', 'escalation_due_at', 'resolution_due_at', 'first_response_at',
        'solution', 'solution_by_id', 'solved_at', 'closed_by_id', 'closed_at', 'cancellation_reason',
        'cancelled_at', 'reopen_count', 'reopened_at', 'last_activity_at', 'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'is_draft' => 'boolean', 'submitted_at' => 'datetime', 'response_due_at' => 'datetime',
            'escalation_due_at' => 'datetime', 'resolution_due_at' => 'datetime', 'first_response_at' => 'datetime',
            'solved_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime',
            'reopened_at' => 'datetime', 'last_activity_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(TicketSubcategory::class, 'ticket_subcategory_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(TicketForm::class, 'ticket_form_id');
    }

    public function requesterArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'requester_area_id');
    }

    public function requesterDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'requester_department_id');
    }

    public function impact(): BelongsTo
    {
        return $this->belongsTo(ImpactLevel::class, 'impact_level_id');
    }

    public function urgency(): BelongsTo
    {
        return $this->belongsTo(UrgencyLevel::class, 'urgency_level_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TicketStatus::class, 'ticket_status_id');
    }

    public function currentArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'current_it_area_id');
    }

    public function currentGroup(): BelongsTo
    {
        return $this->belongsTo(TechnicalGroup::class, 'current_it_group_id');
    }

    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assignee_id');
    }

    public function solutionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solution_by_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(TicketFieldValue::class);
    }

    public function equipmentLinks(): HasMany
    {
        return $this->hasMany(TicketEquipmentLink::class);
    }

    public function supplyUsages(): HasMany
    {
        return $this->hasMany(TicketSupplyUsage::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TicketApproval::class)->orderBy('level_number');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class)->latest();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->latest('activity_date')->latest('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->oldest();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class)->latest('created_at')->latest('id');
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(TicketRelation::class);
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(TicketRelation::class, 'related_ticket_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(TicketReview::class);
    }

    public function developmentRequest(): HasOne
    {
        return $this->hasOne(DevelopmentRequest::class);
    }

    public function maintenanceOrder(): HasOne
    {
        return $this->hasOne(MaintenanceOrder::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class)->latest('escalated_at');
    }

    public function thirdPartyDependencies(): HasMany
    {
        return $this->hasMany(TicketThirdPartyDependency::class)->latest('requested_at');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(SystemAlert::class)->latest('detected_at');
    }

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? str($this->source)->headline()->toString();
    }

    public function getApprovalStatusLabelAttribute(): string
    {
        return self::APPROVAL_STATUSES[$this->approval_status] ?? str($this->approval_status)->headline()->toString();
    }

    public function isTerminal(): bool
    {
        return in_array($this->status?->slug, ['cerrado', 'cancelado'], true);
    }
}

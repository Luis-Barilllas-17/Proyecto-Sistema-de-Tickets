<?php

namespace App\Policies;

use App\Models\ApprovalDelegation;
use App\Models\Ticket;
use App\Models\TicketApproval;
use App\Models\User;

class TicketPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->hasPermission('tickets.view_all')) {
            return true;
        }

        if ($ticket->requester_id === $user->id || $ticket->created_by_id === $user->id) {
            return true;
        }

        if ($user->hasPermission('tickets.view_assigned')) {
            if ($ticket->current_assignee_id === $user->id) {
                return true;
            }
            if ($ticket->current_it_group_id && $user->technicalGroups()->whereKey($ticket->current_it_group_id)->exists()) {
                return true;
            }
        }

        return $ticket->approvals()->where('status', 'pending')->get()
            ->contains(fn (TicketApproval $approval) => $this->isAuthorizedApprover($user, $approval));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    public function createInternal(User $user): bool
    {
        return $user->hasPermission('tickets.create_internal');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        $ownsEditableSource = $ticket->source === 'it_internal'
            ? $ticket->created_by_id === $user->id && $user->hasPermission('tickets.create_internal')
            : $ticket->requester_id === $user->id;

        return $ownsEditableSource
            && ($ticket->is_draft || $ticket->approval_status === 'changes_requested')
            && ! $ticket->isTerminal();
    }

    public function approve(User $user, Ticket $ticket): bool
    {
        if (! $user->hasPermission('tickets.approve')) {
            return false;
        }

        $approval = $ticket->approvals()->where('status', 'pending')->first();

        return $approval !== null && $this->isAuthorizedApprover($user, $approval);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.assign') && $this->view($user, $ticket)
            && ! $ticket->is_draft && ! in_array($ticket->approval_status, ['pending', 'rejected', 'changes_requested'], true)
            && ! $ticket->isTerminal();
    }

    public function transfer(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.transfer') && $this->view($user, $ticket)
            && ! $ticket->is_draft && ! in_array($ticket->approval_status, ['pending', 'rejected', 'changes_requested'], true)
            && ! $ticket->isTerminal();
    }

    public function respondAssignment(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.accept_assignment')
            && $ticket->current_assignee_id === $user->id
            && $ticket->assignment_status === 'pending';
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.comment') && $this->view($user, $ticket) && ! $ticket->isTerminal();
    }

    public function internalNote(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.internal_note') && $this->view($user, $ticket) && ! $ticket->isTerminal();
    }

    public function logActivity(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.activity') && $this->view($user, $ticket) && ! $ticket->isTerminal();
    }

    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.status') && $this->view($user, $ticket) && ! $ticket->isTerminal();
    }

    public function solve(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.solve') && $this->view($user, $ticket)
            && ! $ticket->is_draft && ! in_array($ticket->approval_status, ['pending', 'rejected', 'changes_requested'], true)
            && ! $ticket->isTerminal();
    }

    public function relate(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.relate') && $this->view($user, $ticket);
    }

    public function escalate(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.escalate') && $this->view($user, $ticket)
            && ! $ticket->is_draft && ! $ticket->isTerminal();
    }

    public function manageThirdParty(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.third_party') && $this->view($user, $ticket)
            && ! $ticket->is_draft && ! $ticket->isTerminal();
    }

    public function reserveSupply(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('inventory.consume') && $this->view($user, $ticket)
            && ! $ticket->is_draft
            && ! in_array($ticket->approval_status, ['pending', 'rejected', 'changes_requested'], true)
            && ! $ticket->isTerminal();
    }

    public function review(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.review')
            && $ticket->requester_id === $user->id
            && $ticket->source === 'requester'
            && $ticket->status?->slug === 'solucionado';
    }

    public function reopen(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.reopen')
            && $ticket->requester_id === $user->id
            && $ticket->source === 'requester'
            && in_array($ticket->status?->slug, ['solucionado', 'cerrado'], true);
    }

    public function cancel(User $user, Ticket $ticket): bool
    {
        return ! $ticket->isTerminal()
            && (($ticket->source === 'requester' && $ticket->requester_id === $user->id) || $user->hasPermission('tickets.status'));
    }

    public function deleteAttachment(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    private function isAuthorizedApprover(User $user, TicketApproval $approval): bool
    {
        if ($approval->approver_user_id === $user->id) {
            return true;
        }

        if ($approval->approver_role_id && $user->roles()->whereKey($approval->approver_role_id)->where('is_active', true)->exists()) {
            return true;
        }

        if (! $approval->approver_user_id || ! $approval->level?->allow_delegation) {
            return false;
        }

        return ApprovalDelegation::where('delegator_user_id', $approval->approver_user_id)
            ->where('delegate_user_id', $user->id)
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->exists();
    }
}

<?php

namespace App\Policies;

use App\Models\DevelopmentRequest;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class DevelopmentRequestPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('development.view');
    }

    public function view(User $user, DevelopmentRequest $developmentRequest): bool
    {
        return $user->hasPermission('development.view')
            && Gate::forUser($user)->allows('view', $developmentRequest->ticket);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('development.create') && $user->hasPermission('tickets.create');
    }

    public function update(User $user, DevelopmentRequest $developmentRequest): bool
    {
        if ($developmentRequest->ticket->isTerminal() || $this->hasPendingDeployment($developmentRequest)) {
            return false;
        }

        return ($user->hasPermission('development.update') && $this->view($user, $developmentRequest))
            || Gate::forUser($user)->allows('update', $developmentRequest->ticket);
    }

    public function manageRequirements(User $user, DevelopmentRequest $developmentRequest): bool
    {
        return $user->hasPermission('development.requirements') && $this->view($user, $developmentRequest)
            && ! $developmentRequest->ticket->isTerminal()
            && ! $this->hasPendingDeployment($developmentRequest);
    }

    public function manageSecurity(User $user, DevelopmentRequest $developmentRequest): bool
    {
        return $user->hasPermission('development.security_manage') && $this->view($user, $developmentRequest)
            && ! $developmentRequest->ticket->isTerminal()
            && ! $this->hasPendingDeployment($developmentRequest);
    }

    public function verifySecurity(User $user, DevelopmentRequest $developmentRequest): bool
    {
        return $user->hasPermission('development.security_verify') && $this->view($user, $developmentRequest)
            && ! $developmentRequest->ticket->isTerminal()
            && ! $this->hasPendingDeployment($developmentRequest);
    }

    public function manageTests(User $user, DevelopmentRequest $developmentRequest): bool
    {
        return $user->hasPermission('development.tests') && $this->view($user, $developmentRequest)
            && ! $developmentRequest->ticket->isTerminal()
            && ! $this->hasPendingDeployment($developmentRequest);
    }

    public function requestDeployment(User $user, DevelopmentRequest $developmentRequest): bool
    {
        return $user->hasPermission('development.deploy_request') && $this->view($user, $developmentRequest)
            && ! $developmentRequest->ticket->is_draft
            && ! in_array($developmentRequest->ticket->approval_status, ['pending', 'rejected', 'changes_requested'], true)
            && ! $developmentRequest->ticket->isTerminal();
    }

    public function authorizeDeployment(User $user, DevelopmentRequest $developmentRequest): bool
    {
        return $user->hasPermission('development.deploy_authorize') && $this->view($user, $developmentRequest)
            && ! $developmentRequest->ticket->isTerminal();
    }

    private function hasPendingDeployment(DevelopmentRequest $developmentRequest): bool
    {
        return $developmentRequest->deploymentAuthorizations()->where('status', 'pending')->exists();
    }
}

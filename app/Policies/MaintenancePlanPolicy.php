<?php

namespace App\Policies;

use App\Models\MaintenancePlan;
use App\Models\User;

class MaintenancePlanPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function view(User $user, MaintenancePlan $plan): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('maintenance.manage_plans');
    }

    public function update(User $user, MaintenancePlan $plan): bool
    {
        return $user->hasPermission('maintenance.manage_plans');
    }

    public function schedule(User $user, MaintenancePlan $plan): bool
    {
        return $user->hasPermission('maintenance.schedule');
    }
}

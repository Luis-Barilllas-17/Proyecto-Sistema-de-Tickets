<?php

namespace App\Policies;

use App\Models\MaintenanceOrder;
use App\Models\User;

class MaintenanceOrderPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function view(User $user, MaintenanceOrder $order): bool
    {
        return $user->hasPermission('maintenance.view');
    }

    public function generate(User $user): bool
    {
        return $user->hasPermission('maintenance.generate_orders');
    }

    public function execute(User $user, MaintenanceOrder $order): bool
    {
        if (! $user->hasPermission('maintenance.execute')
            || in_array($order->status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ($user->hasRole('jefatura-it') || $user->hasRole('encargado-area-it')) {
            return true;
        }

        if ($order->assigned_to_id) {
            return $order->assigned_to_id === $user->id;
        }

        $order->loadMissing('plan');
        if ($order->plan->assigned_it_group_id
            && $user->technicalGroups()->whereKey($order->plan->assigned_it_group_id)->exists()) {
            return true;
        }

        return $order->plan->assigned_it_area_id
            && $user->area_id === $order->plan->assigned_it_area_id;
    }
}

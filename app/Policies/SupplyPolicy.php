<?php

namespace App\Policies;

use App\Models\Supply;
use App\Models\User;

class SupplyPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function view(User $user, Supply $supply): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.stock_manage');
    }

    public function update(User $user, Supply $supply): bool
    {
        return $user->hasPermission('inventory.stock_manage');
    }

    public function moveStock(User $user, Supply $supply): bool
    {
        return $user->hasPermission('inventory.stock_manage') && $supply->is_active;
    }
}

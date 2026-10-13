<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;

class EquipmentPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isSuperAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function view(User $user, Equipment $equipment): bool
    {
        return $user->hasPermission('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('inventory.equipment_manage');
    }

    public function update(User $user, Equipment $equipment): bool
    {
        return $user->hasPermission('inventory.equipment_manage');
    }

    public function move(User $user, Equipment $equipment): bool
    {
        return $user->hasPermission('inventory.assignment_manage') && $equipment->status !== 'retired';
    }

    public function recordTechnicalEvent(User $user, Equipment $equipment): bool
    {
        return $user->hasPermission('inventory.technical_history');
    }

    public function manageLicense(User $user, Equipment $equipment): bool
    {
        return $user->hasPermission('inventory.equipment_manage');
    }
}

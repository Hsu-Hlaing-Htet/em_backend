<?php

namespace App\Policies;

use App\Models\MaintenanceCategory;
use App\Models\User;

class MaintenanceCategoryPolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, MaintenanceCategory $maintenanceCategory): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, MaintenanceCategory $maintenanceCategory): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, MaintenanceCategory $maintenanceCategory): bool
    {
        return $this->isAdmin($user);
    }
}

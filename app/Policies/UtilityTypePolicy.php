<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtilityType;

class UtilityTypePolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, UtilityType $utilityType): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, UtilityType $utilityType): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, UtilityType $utilityType): bool
    {
        return $this->isAdmin($user);
    }
}

<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UtilityRate;

class UtilityRatePolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, UtilityRate $utilityRate): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, UtilityRate $utilityRate): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, UtilityRate $utilityRate): bool
    {
        return $this->isAdmin($user);
    }
}

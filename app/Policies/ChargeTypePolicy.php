<?php

namespace App\Policies;

use App\Models\ChargeType;
use App\Models\User;

class ChargeTypePolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, ChargeType $chargeType): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ChargeType $chargeType): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, ChargeType $chargeType): bool
    {
        return $this->isAdmin($user);
    }
}

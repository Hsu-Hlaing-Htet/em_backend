<?php

namespace App\Policies;

use App\Models\LateFee;
use App\Models\User;

class LateFeePolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, LateFee $lateFee): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, LateFee $lateFee): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, LateFee $lateFee): bool
    {
        return $this->isAdmin($user);
    }
}

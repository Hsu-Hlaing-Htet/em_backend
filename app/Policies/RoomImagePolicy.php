<?php

namespace App\Policies;

use App\Models\RoomImage;
use App\Models\User;

class RoomImagePolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, RoomImage $roomImage): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, RoomImage $roomImage): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, RoomImage $roomImage): bool
    {
        return $this->isAdmin($user);
    }
}

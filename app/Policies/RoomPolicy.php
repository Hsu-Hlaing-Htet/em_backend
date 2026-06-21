<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, Room $room): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, Room $room): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, Room $room): bool
    {
        return $this->isAdmin($user);
    }
}

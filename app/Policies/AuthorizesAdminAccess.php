<?php

namespace App\Policies;

use App\Models\User;

trait AuthorizesAdminAccess
{
    protected function isAdmin(User $user): bool
    {
        $user->loadMissing('role');

        return in_array($user->role?->name, ['super_admin', 'admin'], true);
    }
}

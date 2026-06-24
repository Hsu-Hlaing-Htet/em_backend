<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;

class PaymentMethodPolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->isAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->isAdmin($user);
    }

    public function delete(User $user, PaymentMethod $paymentMethod): bool
    {
        return $this->isAdmin($user);
    }
}

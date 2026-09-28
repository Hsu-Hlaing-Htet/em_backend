<?php

namespace App\Policies;

use App\Models\ContactInquiry;
use App\Models\User;

class ContactInquiryPolicy
{
    use AuthorizesAdminAccess;

    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    public function view(User $user, ContactInquiry $contactInquiry): bool
    {
        return $this->isAdmin($user);
    }

    public function update(User $user, ContactInquiry $contactInquiry): bool
    {
        return $this->isAdmin($user);
    }
}

<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use App\Support\UserEmail;

trait ValidatesUserEmail
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('email')) {
            return;
        }

        $this->merge([
            'email' => UserEmail::normalize(is_string($this->input('email')) ? $this->input('email') : ''),
        ]);
    }

    protected function userIdForEmailValidation(): ?int
    {
        $routeUser = $this->route('user');

        if ($routeUser instanceof User) {
            return (int) $routeUser->id;
        }

        if (is_numeric($routeUser)) {
            return (int) $routeUser;
        }

        $authUser = $this->user();

        return $authUser?->id;
    }

    protected function currentEmailForValidation(): ?string
    {
        $routeUser = $this->route('user');

        if ($routeUser instanceof User) {
            return $routeUser->email;
        }

        $userId = $this->userIdForEmailValidation();

        if ($userId === null) {
            return null;
        }

        return User::query()->whereKey($userId)->value('email');
    }

    /**
     * @return array<string, string>
     */
    protected function userEmailMessages(): array
    {
        return UserEmail::messages();
    }
}

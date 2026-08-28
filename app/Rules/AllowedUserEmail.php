<?php

namespace App\Rules;

use App\Support\UserEmail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedUserEmail implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignoreUserId = null,
        private readonly ?string $currentEmail = null,
        private readonly bool $isUpdate = false,
    ) {}

    public static function forCreate(): self
    {
        return new self;
    }

    public static function forUpdate(int $userId, ?string $currentEmail): self
    {
        return new self($userId, $currentEmail, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = UserEmail::normalize(is_string($value) ? $value : '');

        if ($email === '') {
            return;
        }

        $unchanged = $this->isUpdate
            && $this->currentEmail !== null
            && UserEmail::normalize($this->currentEmail) === $email;

        if (UserEmail::isReservedSuperAdminEmail($email)) {
            $ownerId = UserEmail::reservedSuperAdminUserId();

            if ($this->ignoreUserId !== null && $ownerId !== null && $this->ignoreUserId === $ownerId) {
                return;
            }

            $fail(UserEmail::MESSAGE_GMAIL);

            return;
        }

        if ($unchanged) {
            return;
        }

        if (! UserEmail::isGmailAddress($email)) {
            $fail(UserEmail::MESSAGE_GMAIL);
        }
    }
}

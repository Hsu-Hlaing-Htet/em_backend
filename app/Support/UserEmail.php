<?php

namespace App\Support;

use App\Models\User;
use App\Rules\AllowedUserEmail;
use Closure;

/**
 * Account email policy:
 * - Super Admin reserved address: admin@rosewoodroyale.com
 * - All other accounts: unique @gmail.com addresses
 * Roles remain separate from email/domain rules.
 */
final class UserEmail
{
    public const SUPER_ADMIN_EMAIL = 'admin@rosewoodroyale.com';

    public const MESSAGE_REQUIRED = 'Email is required.';

    public const MESSAGE_INVALID = 'Please enter a valid email address.';

    public const MESSAGE_GMAIL = 'Please use a Gmail address.';

    public const MESSAGE_UNIQUE = 'This email is already in use.';

    public static function normalize(?string $email): string
    {
        return strtolower(trim((string) $email));
    }

    public static function isReservedSuperAdminEmail(?string $email): bool
    {
        return self::normalize($email) === self::SUPER_ADMIN_EMAIL;
    }

    public static function isGmailAddress(?string $email): bool
    {
        $normalized = self::normalize($email);

        return $normalized !== '' && str_ends_with($normalized, '@gmail.com');
    }

    public static function reservedSuperAdminUserId(): ?int
    {
        $id = User::query()
            ->whereRaw('LOWER(email) = ?', [self::SUPER_ADMIN_EMAIL])
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function uniqueRule(?int $ignoreUserId = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($ignoreUserId): void {
            $email = self::normalize(is_string($value) ? $value : '');

            if ($email === '') {
                return;
            }

            $query = User::query()->whereRaw('LOWER(email) = ?', [$email]);

            if ($ignoreUserId !== null) {
                $query->where('id', '!=', $ignoreUserId);
            }

            if ($query->exists()) {
                $fail(self::MESSAGE_UNIQUE);
            }
        };
    }

    /**
     * @return list<mixed>
     */
    public static function createRules(): array
    {
        return [
            'required',
            'email',
            'max:255',
            AllowedUserEmail::forCreate(),
            self::uniqueRule(),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function updateRules(int $userId, ?string $currentEmail = null): array
    {
        return [
            'required',
            'email',
            'max:255',
            AllowedUserEmail::forUpdate($userId, $currentEmail),
            self::uniqueRule($userId),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'email.required' => self::MESSAGE_REQUIRED,
            'email.email' => self::MESSAGE_INVALID,
            'email.unique' => self::MESSAGE_UNIQUE,
        ];
    }
}

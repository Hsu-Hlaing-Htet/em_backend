<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * @return array{user: User, token: string}
     */
    public function login(string $email, string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['Email not found.'],
            ]);
        }

        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Incorrect password.'],
            ]);
        }

        if ($user->status !== User::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'email' => ['This account is inactive.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return [
            'user' => $user->load(['role', 'profile']),
            'token' => $token,
        ];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function currentUser(User $user): User
    {
        return $user->load(['role', 'profile']);
    }

    public function sendPasswordResetLink(string $email): void
    {
        $email = trim($email);

        try {
            if ($this->allowsLocalPasswordResetDeliveryTest()) {
                $this->sendLocalPasswordResetDeliveryTest($email);

                return;
            }

            $this->sendSecurePasswordResetLink($email);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Production / default behavior: issue a reset token only for an existing
     * account and email that same account address.
     */
    private function sendSecurePasswordResetLink(string $email): void
    {
        Password::sendResetLink(
            ['email' => $email],
            function (User $user, string $token) use ($email): void {
                if (strcasecmp($user->getEmailForPasswordReset(), $email) !== 0) {
                    return;
                }

                $user->notify(new ResetPasswordNotification($token, $email));
            }
        );
    }

    /**
     * LOCAL TESTING ONLY. Sends the existing reset template to the typed
     * address so Gmail SMTP delivery can be verified. Disabled unless
     * APP_ENV=local and PASSWORD_RESET_LOCAL_DELIVERY_TEST=true.
     */
    private function allowsLocalPasswordResetDeliveryTest(): bool
    {
        return app()->environment('local')
            && filter_var(config('auth.password_reset_local_delivery_test'), FILTER_VALIDATE_BOOLEAN);
    }

    private function sendLocalPasswordResetDeliveryTest(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            $this->sendSecurePasswordResetLink($email);

            return;
        }

        Notification::route('mail', $email)->notify(
            new ResetPasswordNotification(Str::random(64), $email)
        );
    }

    public function resetPassword(array $credentials): void
    {
        $status = Password::reset(
            $credentials,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }
    }

    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from the current password.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($newPassword),
        ])->save();

        $user->tokens()->delete();
    }
}

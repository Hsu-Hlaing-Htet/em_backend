<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

uses(RefreshDatabase::class);

function passwordResetAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

test('forgot password sends reset notification for existing user', function () {
    Notification::fake();

    $admin = passwordResetAdmin();

    $this->postJson('/api/auth/forgot-password', [
        'email' => $admin->email,
    ])->assertOk()
        ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

    Notification::assertSentTo($admin, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($admin) {
        $mail = $notification->toMail($admin);
        $expected = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query(
            [
                'token' => $notification->token,
                'email' => $admin->email,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );

        $html = (string) $mail->render();
        $expireMinutes = (int) config('auth.passwords.users.expire');

        expect($notification->token)->not->toBe('')
            ->and($notification->recipientEmail)->toBe($admin->email)
            ->and($admin->routeNotificationForMail($notification))->toBe($admin->email)
            ->and($mail->actionUrl)->toBe($expected)
            ->and($mail->actionUrl)->toContain('/reset-password?token=')
            ->and($mail->actionUrl)->toContain('email='.urlencode($admin->email))
            ->and($html)->toContain('href="'.e($expected).'"')
            ->and($html)->toContain('Reset Your Password')
            ->and($html)->toContain('We received a request to reset the password for your Rosewood Royale account.')
            ->and($html)->toContain('This password reset link will expire in '.$expireMinutes.' minutes.')
            ->and($html)->toContain('If you did not request a password reset, you can safely ignore this email.')
            ->and($html)->toContain('&copy; 2026 '.config('app.name').'. All rights reserved.')
            ->and($html)->toContain('bgcolor="#80152F"')
            ->and($html)->toContain('background-color:#80152F')
            ->and($html)->toContain('color:#ffffff')
            ->and($html)->toContain('width="70%"')
            ->and($html)->toContain('padding:8px 16px')
            ->and($html)->toContain('border-radius:6px')
            ->and($html)->toContain('border:none')
            ->and($html)->toContain('text-decoration:none')
            ->and($html)->not->toContain('cid:')
            ->and($html)->toContain('alt="Rosewood Royale"')
            ->and($html)->toContain('<img')
            ->and($html)->toContain($notification->token)
            ->and($html)->not->toContain('Hello!');

        return true;
    });
});

test('password reset email template uses the branded layout and dynamic reset url', function () {
    Notification::fake();

    $admin = passwordResetAdmin();

    $this->postJson('/api/auth/forgot-password', [
        'email' => $admin->email,
    ])->assertOk();

    Notification::assertSentTo($admin, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($admin) {
        $mail = $notification->toMail($admin);
        $html = (string) $mail->render();
        $logoSrc = $notification->brandLogoSrc();
        $escapedUrl = e($mail->actionUrl);

        expect($mail->actionText)->toBe('Reset Password')
            ->and($mail->attachments)->toBeEmpty()
            ->and($mail->rawAttachments)->toBeEmpty()
            ->and($logoSrc)->toBe('https://rosewoodroyale.test/images/logo-dark.jpg')
            ->and($html)->toContain('src="'.e((string) $logoSrc).'"')
            ->and($html)->toContain('alt="Rosewood Royale"')
            ->and($html)->toContain('<img')
            ->and($html)->toContain($escapedUrl)
            ->and($html)->toContain('Reset Password')
            ->and($html)->toContain('bgcolor="#80152F"')
            ->and($html)->not->toContain('cid:')
            ->and($html)->not->toContain('example-token')
            ->and(substr_count($html, $escapedUrl))->toBeGreaterThanOrEqual(2);

        return true;
    });
});

test('forgot password sends the reset email to the submitted address only', function () {
    Notification::fake();

    $admin = passwordResetAdmin();
    $other = User::query()->where('email', 'aungaung@rosewoodroyale.com')->firstOrFail();
    $submittedEmail = $admin->email;

    $this->postJson('/api/auth/forgot-password', [
        'email' => "  {$submittedEmail}  ",
    ])->assertOk();

    Notification::assertSentTo($admin, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use ($submittedEmail) {
        return $notification->recipientEmail === $submittedEmail;
    });
    Notification::assertNotSentTo($other, ResetPasswordNotification::class);
});

test('reset password rejects a token when the email belongs to a different user', function () {
    $admin = passwordResetAdmin();
    $other = User::query()->where('email', 'aungaung@rosewoodroyale.com')->firstOrFail();
    $token = Password::createToken($admin);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $other->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect(Hash::check('p@ssword', $other->fresh()->password))->toBeTrue()
        ->and(Hash::check('p@ssword', $admin->fresh()->password))->toBeTrue();
});

test('forgot password returns success even when email is unknown', function () {
    Notification::fake();

    passwordResetAdmin();

    $this->postJson('/api/auth/forgot-password', [
        'email' => 'unknown@rosewoodroyale.com',
    ])->assertOk()
        ->assertJsonPath('message', 'If an account exists for that email, a password reset link has been sent.');

    Notification::assertNothingSent();
});

test('local delivery test can email an unregistered address only in local', function () {
    Notification::fake();
    passwordResetAdmin();

    $this->app['env'] = 'local';
    config(['auth.password_reset_local_delivery_test' => true]);

    $email = 'delivery-test@gmail.com';

    $this->postJson('/api/auth/forgot-password', [
        'email' => $email,
    ])->assertOk();

    Notification::assertSentOnDemand(ResetPasswordNotification::class, function (ResetPasswordNotification $notification, array $channels, $notifiable) use ($email) {
        return $notification->recipientEmail === $email
            && ($notifiable->routes['mail'] ?? null) === $email
            && str_contains($notification->toMail($notifiable)->actionUrl, 'email='.urlencode($email));
    });
});

test('local delivery test stays disabled outside the local environment', function () {
    Notification::fake();
    passwordResetAdmin();
    config(['auth.password_reset_local_delivery_test' => true]);

    $this->postJson('/api/auth/forgot-password', [
        'email' => 'delivery-test@gmail.com',
    ])->assertOk();

    Notification::assertNothingSent();
});

test('reset password updates credentials and revokes tokens', function () {
    $admin = passwordResetAdmin();
    $token = Password::createToken($admin);

    $admin->createToken('auth-token');

    expect($admin->tokens()->count())->toBe(1);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => $admin->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertOk()
        ->assertJsonPath('message', 'Your password has been reset successfully.');

    $admin->refresh();

    expect(Hash::check('new-password-123', $admin->password))->toBeTrue()
        ->and($admin->tokens()->count())->toBe(0);
});

test('reset password rejects invalid token', function () {
    $admin = passwordResetAdmin();

    $this->postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => $admin->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('reset password email logo ignores localhost and filesystem paths', function () {
    config([
        'mail.logo_url' => 'http://localhost:8000/images/logo-dark.jpg',
        'app.url' => 'http://localhost:8000',
        'app.frontend_url' => 'http://localhost:5173',
    ]);

    $notification = new ResetPasswordNotification('token-value', 'admin@rosewoodroyale.com');

    expect($notification->brandLogoUrl())->toBeNull()
        ->and($notification->brandLogoSrc())->toStartWith('data:image/jpeg;base64,');

    config(['mail.logo_url' => 'https://cdn.example.com/brand/logo-dark.jpg']);

    expect($notification->brandLogoUrl())->toBe('https://cdn.example.com/brand/logo-dark.jpg')
        ->and($notification->brandLogoSrc())->toBe('https://cdn.example.com/brand/logo-dark.jpg');
});

test('reset password email always renders a visible logo without mime attachments', function () {
    Notification::fake();

    config([
        'mail.logo_url' => null,
        'app.url' => 'http://localhost:8000',
        'app.frontend_url' => 'http://localhost:5173',
    ]);

    $admin = passwordResetAdmin();

    $this->postJson('/api/auth/forgot-password', [
        'email' => $admin->email,
    ])->assertOk();

    Notification::assertSentTo($admin, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) {
        $mail = $notification->toMail($notification);
        $html = (string) $mail->render();
        $logoSrc = $notification->brandLogoSrc();

        expect($logoSrc)->not->toBeNull()
            ->and($logoSrc)->toStartWith('data:image/jpeg;base64,')
            ->and($html)->toContain('src="'.e((string) $logoSrc).'"')
            ->and($html)->toContain('alt="Rosewood Royale"')
            ->and($mail->attachments)->toBeEmpty()
            ->and($mail->rawAttachments)->toBeEmpty()
            ->and($html)->not->toContain('cid:');

        return true;
    });
});

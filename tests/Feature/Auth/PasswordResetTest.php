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

    Notification::assertSentTo($admin, ResetPasswordNotification::class);
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

<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

function changePasswordAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

test('unauthenticated change password is rejected', function () {
    $this->postJson('/api/auth/change-password', [
        'current_password' => 'p@ssword',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertUnauthorized();
});

test('change password rejects an incorrect current password', function () {
    $admin = changePasswordAdmin();

    $login = $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'p@ssword',
    ])->assertOk();

    $this->withToken($login->json('token'))
        ->postJson('/api/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['current_password'])
        ->assertJsonPath('errors.current_password.0', 'Current password is incorrect.');

    $admin->refresh();

    expect(Hash::check('p@ssword', $admin->password))->toBeTrue()
        ->and($admin->tokens()->count())->toBe(1);
});

test('change password updates only that user and revokes all of their tokens', function () {
    $admin = changePasswordAdmin();
    $other = User::query()->where('email', 'aungaung@rosewoodroyale.com')->firstOrFail();
    $otherPassword = $other->password;

    $login = $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'p@ssword',
    ])->assertOk();

    $token = $login->json('token');
    $admin->createToken('other-device');

    expect($admin->tokens()->count())->toBe(2);

    $this->withToken($token)
        ->postJson('/api/auth/change-password', [
            'current_password' => 'p@ssword',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Your password has been changed successfully.');

    $admin->refresh();
    $other->refresh();

    expect(Hash::check('new-password-123', $admin->password))->toBeTrue()
        ->and(Hash::check('p@ssword', $admin->password))->toBeFalse()
        ->and($admin->tokens()->count())->toBe(0)
        ->and($other->password)->toBe($otherPassword);

    $this->withToken($token)
        ->getJson('/api/auth/me')
        ->assertUnauthorized();

    $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'new-password-123',
    ])->assertOk();
});

test('change password requires confirmation and existing strength rules', function () {
    $admin = changePasswordAdmin();

    $login = $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'p@ssword',
    ])->assertOk();

    $this->withToken($login->json('token'))
        ->postJson('/api/auth/change-password', [
            'current_password' => 'p@ssword',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

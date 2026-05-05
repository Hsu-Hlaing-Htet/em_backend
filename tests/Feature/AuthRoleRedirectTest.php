<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers owner user through api and returns owner redirect', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'phone' => '+95 9 111111111',
        'address' => 'Yangon',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.role', User::ROLE_OWNER)
        ->assertJsonPath('redirect_to', '/user/dashboard');

    $this->assertAuthenticated();
});

it('redirects admin and owner correctly after login', function () {
    User::factory()->create([
        'email' => 'admin@rosewoodroyale.com',
        'role' => User::ROLE_ADMIN,
        'password' => 'password',
    ]);

    User::factory()->create([
        'email' => 'owner@example.com',
        'role' => User::ROLE_OWNER,
        'password' => 'password',
    ]);

    $adminResponse = $this->postJson('/api/auth/login', [
        'email' => 'admin@rosewoodroyale.com',
        'password' => 'password',
    ]);

    $adminResponse->assertOk()->assertJsonPath('redirect_to', '/admin/dashboard');

    $this->postJson('/api/auth/logout')->assertOk();

    $ownerResponse = $this->postJson('/api/auth/login', [
        'email' => 'owner@example.com',
        'password' => 'password',
    ]);

    $ownerResponse->assertOk()->assertJsonPath('redirect_to', '/user/dashboard');
});

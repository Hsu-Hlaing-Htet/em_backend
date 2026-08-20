<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function tb1SeedUsers(): void
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
}

test('valid login returns token and user payload', function () {
    tb1SeedUsers();

    $this->postJson('/api/auth/login', [
        'email' => 'admin@rosewoodroyale.com',
        'password' => 'p@ssword',
    ])
        ->assertOk()
        ->assertJsonPath('message', 'Login successful')
        ->assertJsonStructure([
            'message',
            'token',
            'user' => ['id', 'name', 'email', 'role', 'profile'],
        ])
        ->assertJsonPath('user.email', 'admin@rosewoodroyale.com')
        ->assertJsonPath('user.role', Role::SUPER_ADMIN);
});

test('unknown login email returns validation error on email', function () {
    tb1SeedUsers();

    $this->postJson('/api/auth/login', [
        'email' => 'missing@rosewoodroyale.com',
        'password' => 'p@ssword',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email'])
        ->assertJsonPath('errors.email.0', 'Email not found.');
});

test('invalid login password returns validation error on password', function () {
    tb1SeedUsers();

    $this->postJson('/api/auth/login', [
        'email' => 'admin@rosewoodroyale.com',
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password'])
        ->assertJsonPath('errors.password.0', 'Incorrect password.');
});

test('missing login fields are rejected', function () {
    tb1SeedUsers();

    $this->postJson('/api/auth/login', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});

test('authenticated me endpoint returns current user', function () {
    tb1SeedUsers();
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'admin@rosewoodroyale.com')
        ->assertJsonPath('data.role', Role::SUPER_ADMIN)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'role', 'profile'],
        ]);
});

test('logout succeeds for authenticated user', function () {
    tb1SeedUsers();

    $login = $this->postJson('/api/auth/login', [
        'email' => 'admin@rosewoodroyale.com',
        'password' => 'p@ssword',
    ])->assertOk();

    $token = $login->json('token');
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    expect($admin->tokens()->count())->toBe(1);

    $this->withToken($token)
        ->postJson('/api/auth/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logout successful');

    expect($admin->fresh()->tokens()->count())->toBe(0);
});

test('unauthenticated requests to protected endpoints are rejected', function () {
    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->postJson('/api/auth/logout')->assertUnauthorized();
    $this->postJson('/api/auth/change-password')->assertUnauthorized();
    $this->getJson('/api/buildings')->assertUnauthorized();
});

test('role middleware allows admin and blocks customer on admin routes', function () {
    tb1SeedUsers();

    $admin = User::query()->where('email', 'aungaung@rosewoodroyale.com')->firstOrFail();
    $customer = User::query()->where('email', 'mgmg@rosewoodroyale.com')->firstOrFail();

    expect($admin->role?->name)->toBe(Role::ADMIN);
    expect($customer->role?->name)->toBe(Role::CUSTOMER);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/buildings')
        ->assertOk();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/buildings')
        ->assertForbidden();
});

test('user factory creates admin and customer roles after RoleSeeder', function () {
    (new RoleSeeder)->run();

    $admin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($admin->role->name)->toBe(Role::ADMIN);
    expect($customer->role->name)->toBe(Role::CUSTOMER);
    expect($superAdmin->role->name)->toBe(Role::SUPER_ADMIN);
});

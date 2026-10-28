<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('database seeder creates exactly twenty-five inactive staff across all employment roles idempotently', function (): void {
    $this->seed(DatabaseSeeder::class);
    $staff = User::query()->with('role')->whereHas('role', fn ($query) => $query->whereIn('name', Role::staffRoleNames()))->get();
    expect($staff)->toHaveCount(25)
        ->and($staff->every(fn (User $user): bool => $user->status === User::STATUS_INACTIVE))->toBeTrue()
        ->and($staff->pluck('role.name')->unique()->sort()->values()->all())
        ->toBe(collect(Role::staffRoleNames())->sort()->values()->all());
    $this->seed(DatabaseSeeder::class);
    expect(User::query()->whereHas('role', fn ($query) => $query->whereIn('name', Role::staffRoleNames()))->count())->toBe(25);
});

test('staff roles cannot authenticate or access admin endpoints', function (): void {
    $this->seed(DatabaseSeeder::class);
    $staff = User::query()->where('email', 'may.thazin.win@rosewoodroyale.test')->firstOrFail();
    $staff->update(['status' => User::STATUS_ACTIVE, 'password' => Hash::make('Staff-password-99')]);
    $this->postJson('/api/auth/login', ['email' => $staff->email, 'password' => 'Staff-password-99'])->assertUnprocessable();
    $this->actingAs($staff, 'sanctum')->getJson('/api/staff')->assertForbidden();
});

test('staff directory returns only employment-role staff and excludes administrators', function (): void {
    $this->seed(DatabaseSeeder::class);
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $response = $this->actingAs($admin, 'sanctum')->getJson('/api/staff?per_page=50');
    $response->assertOk()->assertJsonPath('data.total', 25)->assertJsonCount(25, 'data.data')->assertJsonMissing(['email' => $admin->email]);
    expect(collect($response->json('data.data'))->pluck('role_name')->every(fn ($name) => in_array($name, Role::staffRoleNames(), true)))->toBeTrue();
});

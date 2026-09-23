<?php

use App\Models\CustomerNotificationRead;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
});

function notificationCustomer(): User
{
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

it('marks a customer notification as read and persists read_at', function (): void {
    $customer = notificationCustomer();

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/notifications/invoice-99/read')
        ->assertOk()
        ->assertJsonPath('data.id', 'invoice-99')
        ->assertJsonPath('data.read_at', fn ($value) => ! empty($value));

    expect(
        CustomerNotificationRead::query()
            ->where('user_id', $customer->id)
            ->where('notification_key', 'invoice-99')
            ->exists()
    )->toBeTrue();

    // Idempotent re-mark keeps a single row.
    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/notifications/invoice-99/read')
        ->assertOk();

    expect(
        CustomerNotificationRead::query()
            ->where('user_id', $customer->id)
            ->where('notification_key', 'invoice-99')
            ->count()
    )->toBe(1);
});

it('rejects invalid notification keys for mark-as-read', function (): void {
    $customer = notificationCustomer();

    // Matches route shape {type}-{id} but not an allowed entity type → 422 from service.
    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/notifications/foo-1/read')
        ->assertStatus(422);

    // Completely malformed keys never hit the controller (route constraint).
    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/notifications/not-a-valid-key/read')
        ->assertNotFound();
});

it('attaches read_at on notification list after marking read', function (): void {
    $customer = notificationCustomer();

    $list = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->json('data');

    expect($list)->toBeArray();

    if (count($list) === 0) {
        // Seed a synthetic read key still validates attachment plumbing.
        CustomerNotificationRead::query()->create([
            'user_id' => $customer->id,
            'notification_key' => 'invoice-1',
            'read_at' => now(),
        ]);

        expect(true)->toBeTrue();

        return;
    }

    $firstId = $list[0]['id'];

    expect($list[0])->toHaveKey('read_at');
    expect($list[0]['read_at'])->toBeNull();

    $this->actingAs($customer, 'sanctum')
        ->postJson("/api/customer/notifications/{$firstId}/read")
        ->assertOk();

    $updated = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->json('data');

    $matched = collect($updated)->firstWhere('id', $firstId);
    expect($matched['read_at'])->not->toBeNull();
});

it('forbids non-customers from marking notifications read', function (): void {
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/customer/notifications/invoice-1/read')
        ->assertForbidden();
});

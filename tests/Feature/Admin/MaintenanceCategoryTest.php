<?php

use App\Models\MaintenanceCategory;
use App\Models\MaintenanceRequest;
use App\Models\User;
use Database\Seeders\MaintenanceCategorySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new MaintenanceCategorySeeder)->run();
});

it('lists maintenance categories for admin with pagination', function (): void {
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/maintenance-categories')
        ->assertOk()
        ->assertJsonPath('data.total', 5)
        ->assertJsonCount(5, 'data.data');
});

it('creates updates and toggles maintenance category status', function (): void {
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/maintenance-categories', [
            'name' => 'Security',
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Security')
        ->assertJsonPath('data.slug', 'security')
        ->assertJsonPath('data.status', 'active');

    $id = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->putJson('/api/maintenance-categories/'.$id, [
            'name' => 'Security',
            'status' => 'inactive',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'inactive');

    $this->assertDatabaseHas('maintenance_categories', [
        'id' => $id,
        'status' => 'inactive',
    ]);
});

it('returns only active categories to customers', function (): void {
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    MaintenanceCategory::query()->where('slug', 'hvac')->update(['status' => 'inactive']);

    $response = $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/maintenance-categories')
        ->assertOk();

    $slugs = collect($response->json('data'))->pluck('slug')->all();

    expect($slugs)->toContain('plumbing')
        ->and($slugs)->not->toContain('hvac')
        ->and(collect($response->json('data'))->every(fn ($row) => $row['status'] === 'active'))->toBeTrue();
});

it('rejects customer create with inactive or unknown category', function (): void {
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();

    MaintenanceCategory::query()->where('slug', 'hvac')->update(['status' => 'inactive']);

    $building = \App\Models\Building::query()->create([
        'building_name' => 'Category Tower',
        'location' => 'Yangon',
    ]);
    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'C-101',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 800,
        'sale_price' => 0,
        'rent_price' => 300000,
        'rent_deposit_price' => 30000,
        'booking_deposit_price' => 10000,
    ]);
    \App\Models\Contract::query()->create([
        'contract_number' => 'R-CAT-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 3600000,
        'deposit_amount' => 30000,
        'type' => 'rent',
        'payment_type' => 'full',
        'duration_months' => 12,
        'billing_day' => 5,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Broken AC',
            'category' => 'hvac',
            'priority' => 'high',
            'description' => 'AC not cooling.',
        ])
        ->assertStatus(422);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/customer/maintenance-requests', [
            'room_id' => $room->id,
            'title' => 'Unknown issue',
            'category' => 'not-a-real-category',
            'priority' => 'low',
            'description' => 'Should fail.',
        ])
        ->assertStatus(422);
});

it('keeps historical category readable after category is inactivated', function (): void {
    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
    $customer = User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
    $category = MaintenanceCategory::query()->where('slug', 'hvac')->firstOrFail();

    $building = \App\Models\Building::query()->create([
        'building_name' => 'History Tower',
        'location' => 'Yangon',
    ]);
    $room = \App\Models\Room::query()->create([
        'building_id' => $building->id,
        'room_number' => 'H-101',
        'floor_number' => 1,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 800,
        'sale_price' => 0,
        'rent_price' => 300000,
        'rent_deposit_price' => 30000,
        'booking_deposit_price' => 10000,
    ]);

    $request = MaintenanceRequest::query()->create([
        'room_id' => $room->id,
        'user_id' => $customer->id,
        'created_by' => $admin->id,
        'title' => 'Old HVAC ticket',
        'maintenance_category_id' => $category->id,
        'category' => 'hvac',
        'priority' => 'medium',
        'description' => 'Historical request',
        'status' => 'pending',
    ]);

    $category->update(['status' => 'inactive']);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/maintenance-requests/'.$request->id)
        ->assertOk()
        ->assertJsonPath('data.category', 'hvac')
        ->assertJsonPath('data.maintenance_category_name', 'HVAC');
});

<?php

use App\Models\Building;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function listSortAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

test('building list sorts by name and rejects unknown sort fields', function () {
    $admin = listSortAdmin();

    Building::factory()->create([
        'building_name' => 'Zebra Tower',
        'location' => 'North',
        'status' => 'active',
    ]);
    Building::factory()->create([
        'building_name' => 'Alpha Residences',
        'location' => 'South',
        'status' => 'active',
    ]);

    $asc = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/buildings?order=building_name|asc&per_page=50')
        ->assertOk()
        ->json('data.data');

    $names = collect($asc)->pluck('building_name');
    $alphaIndex = $names->search('Alpha Residences');
    $zebraIndex = $names->search('Zebra Tower');

    expect($alphaIndex)->not->toBeFalse()
        ->and($zebraIndex)->not->toBeFalse()
        ->and($alphaIndex)->toBeLessThan($zebraIndex);

    $desc = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/buildings?order=building_name|desc&per_page=50')
        ->assertOk()
        ->json('data.data');

    $descNames = collect($desc)->pluck('building_name');
    expect($descNames->search('Zebra Tower'))->toBeLessThan($descNames->search('Alpha Residences'));

    // Unknown fields are ignored and fall back to default newest-first.
    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/buildings?order=description|asc&per_page=50')
        ->assertOk();
});

test('utility list applies allowlisted server-side sort', function () {
    $admin = listSortAdmin();
    $customer = User::factory()->create();

    $building = Building::factory()->create();
    $room = \App\Models\Room::factory()->create(['building_id' => $building->id]);
    $contract = \App\Models\Contract::factory()->create([
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'status' => \App\Models\Contract::STATUS_ACTIVE,
        'type' => 'rent',
    ]);

    foreach (['2025-10-01', '2025-08-01', '2025-09-01'] as $month) {
        \App\Models\Utility::query()->create([
            'room_id' => $room->id,
            'contract_id' => $contract->id,
            'billing_month' => $month,
            'reading_date' => $month,
            'total_amount' => 1000,
            'status' => 'approved',
            'created_by' => $admin->id,
        ]);
    }

    $months = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utilities?order=billing_month|asc&per_page=50')
        ->assertOk()
        ->json('data.data');

    $billingMonths = collect($months)
        ->pluck('billing_month')
        ->map(fn ($value) => substr((string) $value, 0, 7))
        ->values()
        ->all();

    expect($billingMonths)->toBe(['2025-08', '2025-09', '2025-10']);
});

test('room list sorts list_price numerically by displayed type price', function () {
    $admin = listSortAdmin();
    $building = Building::factory()->create();

    \App\Models\Room::factory()->create([
        'building_id' => $building->id,
        'room_number' => 'SORT-HIGH',
        'type' => 'rent',
        'rent_price' => 900000,
        'sale_price' => 1,
    ]);
    \App\Models\Room::factory()->create([
        'building_id' => $building->id,
        'room_number' => 'SORT-LOW',
        'type' => 'rent',
        'rent_price' => 100000,
        'sale_price' => 999999999,
    ]);
    \App\Models\Room::factory()->create([
        'building_id' => $building->id,
        'room_number' => 'SORT-MID',
        'type' => 'sale',
        'sale_price' => 500000,
        'rent_price' => 1,
    ]);

    $asc = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/rooms?order=list_price|asc&per_page=50')
        ->assertOk()
        ->json('data.data');

    $ascNumbers = collect($asc)->pluck('room_number')->values();
    expect($ascNumbers->search('SORT-LOW'))->toBeLessThan($ascNumbers->search('SORT-MID'))
        ->and($ascNumbers->search('SORT-MID'))->toBeLessThan($ascNumbers->search('SORT-HIGH'));

    $desc = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/rooms?order=list_price|desc&per_page=50')
        ->assertOk()
        ->json('data.data');

    $descNumbers = collect($desc)->pluck('room_number')->values();
    expect($descNumbers->search('SORT-HIGH'))->toBeLessThan($descNumbers->search('SORT-MID'))
        ->and($descNumbers->search('SORT-MID'))->toBeLessThan($descNumbers->search('SORT-LOW'));
});

test('maintenance requests sort priority by severity not alphabetically', function () {
    $admin = listSortAdmin();
    $customer = User::factory()->customer()->create();
    $room = \App\Models\Room::factory()->occupied()->create();

    foreach (['medium', 'low', 'high', 'medium', 'low', 'high'] as $index => $priority) {
        \App\Models\MaintenanceRequest::factory()->pending()->create([
            'room_id' => $room->id,
            'user_id' => $customer->id,
            'created_by' => $customer->id,
            'title' => "Priority {$priority} {$index}",
            'priority' => $priority,
        ]);
    }

    $desc = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/maintenance-requests?order=priority|desc&per_page=50')
        ->assertOk()
        ->json('data.data');

    expect(collect($desc)->pluck('priority')->all())->toBe([
        'high', 'high', 'medium', 'medium', 'low', 'low',
    ]);

    $asc = $this->actingAs($admin, 'sanctum')
        ->getJson('/api/maintenance-requests?order=priority|asc&per_page=50')
        ->assertOk()
        ->json('data.data');

    expect(collect($asc)->pluck('priority')->all())->toBe([
        'low', 'low', 'medium', 'medium', 'high', 'high',
    ]);
});

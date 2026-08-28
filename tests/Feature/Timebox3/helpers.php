<?php

use App\Models\Building;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use App\Models\UtilityRate;
use App\Models\UtilityType;
use Database\Seeders\ChargeTypeSeeder;
use Database\Seeders\LateFeeSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\UtilityRateSeeder;
use Database\Seeders\UtilityTypeSeeder;
use Illuminate\Support\Carbon;

function tb3Admin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
    (new ChargeTypeSeeder)->run();
    (new UtilityTypeSeeder)->run();
    (new UtilityRateSeeder)->run();
    (new LateFeeSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function tb3Customer(): User
{
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function tb3OtherCustomer(): User
{
    return User::query()->where('email', 'hlahla@gmail.com')->firstOrFail();
}

/**
 * Active rent contract stack for straightforward utility API tests.
 *
 * @return array{building: Building, room: Room, contract: Contract}
 */
function tb3ActiveContract(User $admin, User $customer, string $roomNumber = 'TB3-101'): array
{
    $building = Building::query()->create([
        'building_name' => 'TB3 Utility Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $roomNumber,
        'floor_number' => 3,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 800000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-TB3-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'deposit_amount' => 800000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => null,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->toDateString(),
    ]);

    return compact('building', 'room', 'contract');
}

/**
 * @return array{room: Room, contract: Contract, utilityType: UtilityType, rate: UtilityRate}
 */
function tb3RentPeriodStack(User $admin, User $customer, string $roomNumber, Carbon $start, ?Carbon $end = null): array
{
    $building = Building::query()->create([
        'building_name' => 'Utility Period Tower '.$roomNumber,
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $roomNumber,
        'floor_number' => 2,
        'type' => 'rent',
        'status' => 'occupied',
        'area_sqft' => 900,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 800000,
        'booking_deposit_price' => 0,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'R-UP-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'deposit_amount' => 800000,
        'type' => 'rent',
        'payment_type' => 'full',
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end?->toDateString(),
    ]);

    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = UtilityRate::query()
        ->where('utility_type_id', $utilityType->id)
        ->where('status', 'active')
        ->firstOrFail();

    return compact('room', 'contract', 'utilityType', 'rate');
}

/**
 * @return array{room: Room, contract: Contract, utilityType: UtilityType, rate: UtilityRate}
 */
function tb3SalePeriodStack(User $admin, User $customer, string $roomNumber, Carbon $start, ?Carbon $end = null): array
{
    $building = Building::query()->create([
        'building_name' => 'Utility Sale Tower '.$roomNumber,
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $roomNumber,
        'floor_number' => 3,
        'type' => 'sale',
        'status' => 'sold',
        'area_sqft' => 1000,
        'sale_price' => 250000000,
        'rent_price' => 0,
        'rent_deposit_price' => 0,
        'booking_deposit_price' => 25000000,
    ]);

    $contract = Contract::query()->create([
        'contract_number' => 'S-UP-'.fake()->unique()->numerify('######'),
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 250000000,
        'deposit_amount' => 25000000,
        'type' => 'sale',
        'payment_type' => 'full',
        'status' => 'completed',
        'created_by' => $admin->id,
        'start_date' => $start->toDateString(),
        'end_date' => $end?->toDateString(),
    ]);

    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();
    $rate = UtilityRate::query()
        ->where('utility_type_id', $utilityType->id)
        ->where('status', 'active')
        ->firstOrFail();

    return compact('room', 'contract', 'utilityType', 'rate');
}

/**
 * Stack used for batch utility entry with prior approved readings.
 *
 * @return array{building: Building, room: Room, customer: User, contract: Contract, utilityType: UtilityType}
 */
function tb3BatchStack(User $admin): array
{
    $building = Building::query()->create([
        'building_name' => 'Utility Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => '9A',
        'floor_number' => 9,
        'type' => 'rent',
        'status' => 'available',
        'area_sqft' => 1000,
        'sale_price' => 0,
        'rent_price' => 400000,
        'rent_deposit_price' => 40000,
        'booking_deposit_price' => 8000,
    ]);

    $customer = tb3Customer();

    $contract = Contract::query()->create([
        'contract_number' => 'CTR-UTIL-0001',
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => 4800000,
        'type' => 'rent',
        'payment_type' => 'installment',
        'duration_months' => 12,
        'billing_day' => null,
        'status' => 'active',
        'created_by' => $admin->id,
        'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
        'end_date' => now()->addMonths(11)->endOfMonth()->toDateString(),
    ]);

    $utilityType = UtilityType::query()->where('slug', 'electricity')->firstOrFail();

    return compact('building', 'room', 'customer', 'contract', 'utilityType');
}

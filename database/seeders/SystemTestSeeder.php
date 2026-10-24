<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\ChargeType;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MaintenanceCategory;
use App\Models\PaymentMethod;
use App\Models\Profile;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Models\UtilityType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Deterministic fixtures for Playwright System Testing (PW- / SYSTEM- prefixes).
 * Used only against rosewood_system_test.
 */
class SystemTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ChargeTypeSeeder::class,
            PaymentMethodSeeder::class,
            MaintenanceCategorySeeder::class,
            LateFeeSeeder::class,
            UtilityTypeSeeder::class,
            UtilityRateSeeder::class,
        ]);

        $adminRole = Role::findByName(Role::SUPER_ADMIN);
        $customerRole = Role::findByName(Role::CUSTOMER);

        $admin = $this->makeUser(
            email: 'pw-admin@rosewoodroyale.test',
            name: 'PW System Admin',
            roleId: (int) $adminRole->id,
            phone: '+95942099901',
            nrc: '12/YaKaNa(N)999001',
        );

        $customer = $this->makeUser(
            email: 'pw-customer@rosewoodroyale.test',
            name: 'PW Customer One',
            roleId: (int) $customerRole->id,
            phone: '+95942099902',
            nrc: '12/YaKaNa(N)999002',
        );

        $this->makeUser(
            email: 'pw-other@rosewoodroyale.test',
            name: 'PW Customer Other',
            roleId: (int) $customerRole->id,
            phone: '+95942099903',
            nrc: '12/YaKaNa(N)999003',
        );

        $building = Building::query()->create([
            'building_name' => 'PW-Building-001',
            'location' => 'Yangon',
            'description' => 'System test building',
            'status' => 'active',
        ]);

        $room = Room::query()->create([
            'building_id' => $building->id,
            'room_number' => 'PW-R-101',
            'floor_number' => 1,
            'type' => 'rent',
            'status' => 'occupied',
            'area_sqft' => 850,
            'sale_price' => 0,
            'rent_price' => 400000,
            'rent_deposit_price' => 400000,
            'booking_deposit_price' => 0,
        ]);

        Room::query()->create([
            'building_id' => $building->id,
            'room_number' => 'PW-R-201',
            'floor_number' => 2,
            'type' => 'rent',
            'status' => 'available',
            'area_sqft' => 900,
            'sale_price' => 0,
            'rent_price' => 500000,
            'rent_deposit_price' => 500000,
            'booking_deposit_price' => 0,
        ]);

        $contract = Contract::query()->create([
            'contract_number' => 'PW-CONTRACT-001',
            'user_id' => $customer->id,
            'room_id' => $room->id,
            'contract_total' => 4800000,
            'deposit_amount' => 400000,
            'type' => 'rent',
            'payment_type' => 'full',
            'duration_months' => 12,
            'billing_day' => 1,
            'status' => 'active',
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'start_date' => now()->subMonthsNoOverflow(2)->toDateString(),
            'end_date' => now()->addMonths(10)->toDateString(),
        ]);

        $rentChargeId = ChargeType::query()->where('slug', 'monthly-rent')->value('id')
            ?? ChargeType::query()->value('id');

        $invoice = Invoice::query()->create([
            'invoice_number' => 'PW-INV-001',
            'contract_id' => $contract->id,
            'type' => 'rent',
            'issued_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'billing_month' => now()->startOfMonth()->subMonth()->toDateString(),
            'late_fee' => 0,
            'total_amount' => 400000,
            'status' => Invoice::STATUS_ISSUED,
            'created_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'charge_type_id' => $rentChargeId,
            'description' => 'PW Monthly Rent',
            'amount' => 400000,
        ]);

        PaymentMethod::query()->updateOrCreate(
            ['slug' => 'pw-bank-transfer'],
            [
                'name' => 'PW Bank Transfer',
                'type' => PaymentMethod::TYPE_BANK_TRANSFER,
                'status' => PaymentMethod::STATUS_ACTIVE,
                'account_name' => 'Rosewood System Test',
                'account_number' => 'PW-000111',
                'instructions' => 'System test payment method',
                'is_customer_visible' => true,
                'sort_order' => 1,
            ]
        );

        if (! MaintenanceCategory::query()->where('status', 'active')->exists()) {
            MaintenanceCategory::query()->create([
                'name' => 'Electrical',
                'status' => 'active',
            ]);
        }

        if (! UtilityType::query()->where('status', 'active')->exists()) {
            UtilityType::query()->create([
                'name' => 'Electricity',
                'status' => 'active',
            ]);
        }
    }

    private function makeUser(string $email, string $name, int $roleId, string $phone, string $nrc): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('p@ssword'),
                'role_id' => $roleId,
                'status' => 'active',
                'must_change_password' => false,
            ]
        );

        Profile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone' => $phone,
                'nrc' => $nrc,
                'dob' => '1990-01-01',
                'gender' => 'male',
                'address' => 'PW System Test Address',
            ]
        );

        return $user;
    }
}

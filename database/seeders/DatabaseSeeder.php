<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\MeterReading;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\User;
use App\Models\ViewingRequest;
use App\Services\InvoiceGenerationService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::create([
                'name' => 'Rosewood Admin',
                'email' => 'admin@rosewoodroyale.com',
                'phone' => '+95 9 55000001',
                'address' => 'Rosewood Royale HQ',
                'role' => User::ROLE_ADMIN,
                'password' => Hash::make('password'),
            ]);

            $ownerOne = User::create([
                'name' => 'Aung Aung',
                'email' => 'aungaung@gmail.com',
                'phone' => '+95 9 55000011',
                'address' => 'Bahan Township, Yangon',
                'role' => User::ROLE_OWNER,
                'password' => Hash::make('password'),
            ]);

            $ownerTwo = User::create([
                'name' => 'Mya Mya',
                'email' => 'mya@example.com',
                'phone' => '+95 9 55000022',
                'address' => 'Kamayut Township, Yangon',
                'role' => User::ROLE_OWNER,
                'password' => Hash::make('password'),
            ]);

            $tenantOne = Tenant::create([
                'tenant_name' => 'Kyaw Win',
                'phone' => '+95 9 421000001',
                'nrc' => '12/BaKaTa(N)123456',
                'address' => 'Sanchaung Township, Yangon',
            ]);

            $tenantTwo = Tenant::create([
                'tenant_name' => 'Thandar Tun',
                'phone' => '+95 9 421000002',
                'nrc' => '13/LaKaNa(N)654321',
                'address' => 'Hlaing Township, Yangon',
            ]);

            $properties = collect([
                [
                    'property_code' => 'RR-S-0001',
                    'property_name' => 'Rosewood Sky Condo 8A',
                    'property_type' => Property::TYPE_CONDO,
                    'purpose' => Property::PURPOSE_SALE,
                    'owner_user_id' => $ownerOne->id,
                    'building' => 'Sky Residence',
                    'floor' => '8',
                    'unit_number' => 'A',
                    'township' => 'Yankin',
                    'address' => 'No. 12, Golden Valley Road, Yankin',
                    'bedrooms' => 3,
                    'bathrooms' => 2,
                    'area_sqft' => 1650,
                    'status' => Property::STATUS_RESERVED,
                    'sale_price' => 120000,
                    'monthly_rent' => null,
                    'maintenance_fee' => 180,
                    'description' => 'Premium city-view condo in Yankin township.',
                    'featured_image' => 'https://images.unsplash.com/photo-1494526585095-c41746248156',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1494526585095-c41746248156',
                        'https://images.unsplash.com/photo-1560185007-cde436f6a4d0',
                    ],
                    'is_featured' => true,
                    'listed_at' => now()->subDays(15)->toDateString(),
                ],
                [
                    'property_code' => 'RR-R-0001',
                    'property_name' => 'Rosewood Lake Apartment 4C',
                    'property_type' => Property::TYPE_APARTMENT,
                    'purpose' => Property::PURPOSE_RENT,
                    'owner_user_id' => $ownerTwo->id,
                    'building' => 'Lake Tower',
                    'floor' => '4',
                    'unit_number' => 'C',
                    'township' => 'Bahan',
                    'address' => 'No. 88, University Avenue, Bahan',
                    'bedrooms' => 2,
                    'bathrooms' => 2,
                    'area_sqft' => 1100,
                    'status' => Property::STATUS_OCCUPIED,
                    'sale_price' => null,
                    'monthly_rent' => 1400,
                    'maintenance_fee' => 120,
                    'description' => 'Modern apartment near major schools and lake district.',
                    'featured_image' => 'https://images.unsplash.com/photo-1484154218962-a197022b5858',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1484154218962-a197022b5858',
                        'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85',
                    ],
                    'is_featured' => true,
                    'listed_at' => now()->subDays(22)->toDateString(),
                ],
                [
                    'property_code' => 'RR-S-0002',
                    'property_name' => 'Rosewood Garden House',
                    'property_type' => Property::TYPE_HOUSE,
                    'purpose' => Property::PURPOSE_SALE,
                    'owner_user_id' => $ownerTwo->id,
                    'building' => null,
                    'floor' => null,
                    'unit_number' => null,
                    'township' => 'Hlaing',
                    'address' => 'No. 5, Garden Road, Hlaing',
                    'bedrooms' => 4,
                    'bathrooms' => 3,
                    'area_sqft' => 3200,
                    'status' => Property::STATUS_AVAILABLE,
                    'sale_price' => 230000,
                    'monthly_rent' => null,
                    'maintenance_fee' => 90,
                    'description' => 'Freehold detached house with landscaped garden.',
                    'featured_image' => 'https://images.unsplash.com/photo-1570129477492-45c003edd2be',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1570129477492-45c003edd2be',
                        'https://images.unsplash.com/photo-1600585154340-be6161a56a0c',
                    ],
                    'is_featured' => true,
                    'listed_at' => now()->subDays(10)->toDateString(),
                ],
                [
                    'property_code' => 'RR-R-0002',
                    'property_name' => 'Rosewood Metro Condo 12B',
                    'property_type' => Property::TYPE_CONDO,
                    'purpose' => Property::PURPOSE_RENT,
                    'owner_user_id' => $ownerOne->id,
                    'building' => 'Metro Heights',
                    'floor' => '12',
                    'unit_number' => 'B',
                    'township' => 'Sanchaung',
                    'address' => 'No. 77, Pyay Road, Sanchaung',
                    'bedrooms' => 2,
                    'bathrooms' => 2,
                    'area_sqft' => 1250,
                    'status' => Property::STATUS_AVAILABLE,
                    'sale_price' => null,
                    'monthly_rent' => 1650,
                    'maintenance_fee' => 140,
                    'description' => 'High-floor condo for executive rental.',
                    'featured_image' => 'https://images.unsplash.com/photo-1460317442991-0ec209397118',
                    'gallery_images' => [
                        'https://images.unsplash.com/photo-1460317442991-0ec209397118',
                        'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2',
                    ],
                    'is_featured' => true,
                    'listed_at' => now()->subDays(5)->toDateString(),
                ],
            ])->map(fn (array $property) => Property::create($property));

            $saleContract = Contract::create([
                'contract_code' => 'CNT-S-2026-0001',
                'contract_type' => Contract::TYPE_SALE,
                'related_property_id' => $properties[0]->id,
                'owner_id' => $ownerOne->id,
                'start_date' => now()->subDays(7)->toDateString(),
                'end_date' => now()->addMonths(6)->toDateString(),
                'payment_plan' => 'installment',
                'number_of_months' => 6,
                'monthly_due_day' => 5,
                'status' => Contract::STATUS_ACTIVE,
                'terms' => 'Reservation valid for 30 days. Installment plan approved.',
            ]);

            $leaseContract = Contract::create([
                'contract_code' => 'CNT-L-2026-0001',
                'contract_type' => Contract::TYPE_LEASE,
                'related_property_id' => $properties[1]->id,
                'owner_id' => $ownerTwo->id,
                'tenant_id' => $tenantOne->id,
                'start_date' => now()->subMonth()->startOfMonth()->toDateString(),
                'end_date' => now()->addMonths(11)->endOfMonth()->toDateString(),
                'payment_plan' => 'monthly',
                'number_of_months' => 12,
                'monthly_due_day' => 3,
                'status' => Contract::STATUS_ACTIVE,
                'terms' => 'Rent due every month before day 3. Deposit collected.',
            ]);

            /** @var InvoiceGenerationService $invoiceGenerationService */
            $invoiceGenerationService = app(InvoiceGenerationService::class);
            /** @var PaymentService $paymentService */
            $paymentService = app(PaymentService::class);

            $saleInvoices = $invoiceGenerationService->generate(
                contract: $saleContract,
                mode: 'installment',
                totalAmount: 90000,
                firstDueDate: Carbon::parse(now()->startOfMonth()->addDays(5)),
                numberOfMonths: 6,
                monthlyDueDay: 5
            );

            $rentInvoice = $invoiceGenerationService->generate(
                contract: $leaseContract,
                mode: 'rent',
                totalAmount: 1520,
                firstDueDate: Carbon::parse(now()->startOfMonth()->addDays(3)),
                lineItems: [
                    [
                        'item_type' => 'monthly_rent',
                        'description' => 'Monthly rent',
                        'quantity' => 1,
                        'unit_price' => 1400,
                    ],
                    [
                        'item_type' => 'maintenance_fee',
                        'description' => 'Maintenance fee',
                        'quantity' => 1,
                        'unit_price' => 120,
                    ],
                ]
            )->first();

            $paymentService->record($saleInvoices->first(), [
                'payment_date' => now()->subDay()->toDateString(),
                'amount' => 15000,
                'payment_method' => 'bank_transfer',
                'reference_note' => 'Down payment via KBZ transfer',
                'recorded_by_user_id' => $admin->id,
            ]);

            $paymentService->record($rentInvoice, [
                'payment_date' => now()->subDays(2)->toDateString(),
                'amount' => 1520,
                'payment_method' => 'cash',
                'reference_note' => 'Office counter payment',
                'recorded_by_user_id' => $admin->id,
            ]);

            MeterReading::create([
                'property_id' => $properties[1]->id,
                'contract_id' => $leaseContract->id,
                'meter_type' => MeterReading::TYPE_ELECTRICITY,
                'previous_reading' => 5200,
                'current_reading' => 5385,
                'usage' => 185,
                'rate_per_unit' => 0.18,
                'calculated_amount' => 33.30,
                'reading_date' => now()->subDays(4)->toDateString(),
                'recorded_by_user_id' => $admin->id,
            ]);

            MeterReading::create([
                'property_id' => $properties[1]->id,
                'contract_id' => $leaseContract->id,
                'meter_type' => MeterReading::TYPE_WATER,
                'previous_reading' => 950,
                'current_reading' => 990,
                'usage' => 40,
                'rate_per_unit' => 0.10,
                'calculated_amount' => 4.00,
                'reading_date' => now()->subDays(4)->toDateString(),
                'recorded_by_user_id' => $admin->id,
            ]);

            ViewingRequest::create([
                'property_id' => $properties[2]->id,
                'requester_name' => 'Nay Lin',
                'email' => 'naylin@gmail.com',
                'phone' => '+95 9 420000000',
                'message' => 'I would like to schedule a viewing this weekend.',
                'preferred_date' => now()->addDays(3)->toDateString(),
                'request_type' => 'viewing',
                'status' => 'pending',
            ]);

            ViewingRequest::create([
                'property_id' => $properties[0]->id,
                'requester_name' => 'Su Su',
                'email' => 'susu@gmail.com',
                'phone' => '+95 9 420000001',
                'message' => 'Interested in booking and reservation hold.',
                'preferred_date' => now()->addDays(2)->toDateString(),
                'request_type' => 'booking',
                'status' => 'approved',
                'approved_by_user_id' => $admin->id,
                'reservation_expires_at' => now()->addDays(7),
            ]);
        });
    }
}

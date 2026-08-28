<?php

use App\Contracts\DocumentPdfConverter;
use App\Models\Building;
use App\Models\Contract;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function customerPortalUser(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function customerPortalAdmin(): User
{
    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function customerPortalContract(string $type, string $contractNumber, User $customer, User $admin): Contract
{
    $building = Building::query()->create([
        'building_name' => 'Rosewood Tower',
        'location' => 'Yangon',
    ]);

    $room = Room::query()->create([
        'building_id' => $building->id,
        'room_number' => $type === 'rent' ? 'C-107' : 'A-401',
        'floor_number' => $type === 'rent' ? 1 : 4,
        'type' => $type,
        'status' => $type === 'rent' ? 'occupied' : 'sold',
        'area_sqft' => 900,
        'sale_price' => $type === 'sale' ? 800000000 : 0,
        'rent_price' => $type === 'rent' ? 365000 : 0,
        'rent_deposit_price' => $type === 'rent' ? 365000 : 0,
        'booking_deposit_price' => $type === 'sale' ? 80000000 : 0,
    ]);

    return Contract::query()->create([
        'contract_number' => $contractNumber,
        'user_id' => $customer->id,
        'room_id' => $room->id,
        'contract_total' => $type === 'rent' ? 1095000 : 800000000,
        'deposit_amount' => $type === 'rent' ? 365000 : 80000000,
        'type' => $type,
        'payment_type' => $type === 'rent' ? 'installment' : 'full',
        'duration_months' => $type === 'rent' ? 3 : null,
        'status' => Contract::STATUS_ACTIVE,
        'start_date' => '2027-07-01',
        'created_by' => $admin->id,
        'approved_by' => $admin->id,
        'approved_at' => now(),
    ]);
}

it('returns customer dashboard data', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'active_contracts',
                'completed_contracts',
                'unpaid_invoices',
                'paid_invoices',
                'total_payments',
                'pending_payments',
                'completed_payments',
                'total_paid_amount',
                'recent_payments',
            ],
        ]);
});

it('returns customer profile data', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/profile')
        ->assertOk()
        ->assertJsonPath('data.email', $customer->email);
});

it('returns customer contracts list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/contracts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('downloads customer sale contract with the admin pdf template and customer filename', function (): void {
    $customer = customerPortalUser();
    $admin = customerPortalAdmin();
    $contract = customerPortalContract('sale', 'S-000040', $customer, $admin);

    $adminResponse = $this->actingAs($admin, 'sanctum')
        ->get("/api/sale-contracts/approved/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Sale_Contract_S-000040.pdf"');

    $customerResponse = $this->actingAs($customer, 'sanctum')
        ->get("/api/customer/contracts/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Sale_Contract_S-000040.pdf"');

    expect($customerResponse->getContent())->toBe($adminResponse->getContent());
});

it('downloads customer rent contract with the admin pdf template and customer filename', function (): void {
    $customer = customerPortalUser();
    $admin = customerPortalAdmin();
    $contract = customerPortalContract('rent', 'R-000070', $customer, $admin);

    $adminResponse = $this->actingAs($admin, 'sanctum')
        ->get("/api/rent-contracts/active/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Rent_Contract_R-000070.pdf"');

    $customerResponse = $this->actingAs($customer, 'sanctum')
        ->get("/api/customer/contracts/{$contract->id}/document/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Rent_Contract_R-000070.pdf"');

    expect($customerResponse->getContent())->toBe($adminResponse->getContent());
});

it('allows customers to export the rendered contract preview through the shared pdf converter', function (): void {
    $customer = customerPortalUser();

    app()->bind(DocumentPdfConverter::class, fn () => new class implements DocumentPdfConverter {
        public function convert(string $html): string
        {
            return '%PDF-1.4 customer preview export';
        }
    });

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/document-preview/pdf', [
            'html' => '<html><body><article id="pdf-print">Customer contract preview</article></body></html>',
            'filename' => 'Rosewood_Royale_Sale_Contract_S-000041.pdf',
        ])
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="Rosewood_Royale_Sale_Contract_S-000041.pdf"')
        ->assertSee('%PDF', false);
});

it('returns customer invoices list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/invoices')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer payments list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payments')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer receipts list', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/receipts')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data',
                'total',
            ],
        ]);
});

it('returns customer notifications', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/notifications')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('returns customer payment methods', function (): void {
    $customer = customerPortalUser();

    $this->actingAs($customer, 'sanctum')
        ->getJson('/api/customer/payment-methods')
        ->assertOk()
        ->assertJsonStructure(['data']);
});

it('forbids non-customer access to customer portal routes', function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/customer/dashboard')
        ->assertForbidden();
});

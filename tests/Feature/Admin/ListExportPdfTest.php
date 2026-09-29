<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can export a list pdf with applied filters metadata', function () {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    $admin = User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();

    $response = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/list-exports/pdf', [
            'title' => 'Buildings',
            'filename' => 'buildings.pdf',
            'landscape' => true,
            'generated_by' => 'Admin User',
            'columns' => [
                ['field' => 'building_name', 'header' => 'Building Name'],
                ['field' => 'location', 'header' => 'Location'],
            ],
            'rows' => [
                ['building_name' => 'Rosewood Tower', 'location' => 'Yangon'],
            ],
            'filters' => [
                ['label' => 'Search', 'value' => 'Rosewood'],
            ],
        ]);

    $response->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'attachment; filename="buildings.pdf"');

    expect(str_starts_with($response->getContent(), '%PDF'))->toBeTrue();
});

test('list export view aligns invoice columns without stale late fee column', function () {
    $html = view('exports.list', [
        'title' => 'Invoices',
        'columns' => [
            ['field' => 'invoice_number', 'header' => 'Invoice #'],
            ['field' => 'customer_name', 'header' => 'Customer'],
            ['field' => 'payment_status', 'header' => 'Status'],
        ],
        'rows' => [
            [
                'invoice_number' => 'INV-001',
                'customer_name' => 'Jane Doe',
                'payment_status' => 'Overdue',
            ],
        ],
        'filters' => [],
        'generatedBy' => 'Admin',
        'generatedAt' => '2026-01-01 12:00',
        'landscape' => true,
    ])->render();

    expect($html)->toContain('list-col--invoice_number')
        ->and($html)->toContain('Overdue')
        ->and($html)->not->toContain('Late Fee Rule')
        ->and($html)->toContain('Page ')
        ->and($html)->not->toContain('Page </span>');
});

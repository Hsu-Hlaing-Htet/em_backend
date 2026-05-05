<?php

use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows admin to create update and delete properties', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $createResponse = $this->postJson('/api/admin/properties', [
        'property_code' => 'RR-T-0001',
        'property_name' => 'Test Property',
        'property_type' => Property::TYPE_APARTMENT,
        'purpose' => Property::PURPOSE_RENT,
        'township' => 'Bahan',
        'address' => 'No. 1 Test Street',
        'status' => Property::STATUS_AVAILABLE,
        'monthly_rent' => 1200,
        'maintenance_fee' => 50,
    ]);

    $createResponse->assertCreated();

    $propertyId = $createResponse->json('data.id');

    $this->putJson("/api/admin/properties/{$propertyId}", [
        'property_code' => 'RR-T-0001',
        'property_name' => 'Updated Property',
        'property_type' => Property::TYPE_APARTMENT,
        'purpose' => Property::PURPOSE_RENT,
        'township' => 'Hlaing',
        'address' => 'No. 2 Test Street',
        'status' => Property::STATUS_RESERVED,
        'monthly_rent' => 1300,
        'maintenance_fee' => 70,
    ])->assertOk()->assertJsonPath('data.property_name', 'Updated Property');

    $this->deleteJson("/api/admin/properties/{$propertyId}")
        ->assertOk();

    $this->assertSoftDeleted('properties', ['id' => $propertyId]);
});

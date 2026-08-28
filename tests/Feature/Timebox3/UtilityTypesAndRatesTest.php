<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can retrieve and manage utility types and rates', function () {
    $admin = tb3Admin();

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/utility-types')
        ->assertOk()
        ->assertJsonStructure(['data' => ['data', 'total']]);

    $type = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utility-types', [
            'name' => 'TB3 Water Test',
            'slug' => 'tb3-water-'.uniqid(),
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->json('data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utility-rates', [
            'utility_type_id' => $type['id'],
            'unit_price' => 150,
            'effective_date' => now()->toDateString(),
            'status' => 'active',
        ])
        ->assertCreated()
        ->assertJsonPath('data.unit_price', '150.00');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/utility-rates', [
            'utility_type_id' => 999999,
            'unit_price' => -1,
            'effective_date' => 'not-a-date',
            'status' => 'nope',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['utility_type_id', 'unit_price', 'effective_date', 'status'], 'data');
});

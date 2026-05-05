<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns public landing data endpoints', function () {
    $this->seed();

    $this->getJson('/api/public/properties/featured')
        ->assertOk()
        ->assertJsonStructure([
            'sale',
            'rent',
            'houses',
            'condos',
        ]);

    $this->getJson('/api/public/properties/stats')
        ->assertOk()
        ->assertJsonStructure([
            'total_properties',
            'total_clients',
            'available',
            'occupied',
            'years_of_service',
        ]);

    $this->getJson('/api/public/properties')
        ->assertOk()
        ->assertJsonStructure([
            'current_page',
            'data',
            'last_page',
            'total',
        ]);
});

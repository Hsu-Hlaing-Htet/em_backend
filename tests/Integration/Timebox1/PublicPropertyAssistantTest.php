<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('proxies a public property question to the ai service', function (): void {
    config([
        'ai.base_url' => 'http://ai.test',
    ]);

    Http::fake([
        'http://ai.test/api/v1/property/ask' => Http::response([
            'answer' => 'Two listings match your search.',
            'properties' => [],
            'model' => 'gpt-4o',
        ], 200),
    ]);

    $this->postJson('/api/public/ai/property/ask', [
        'question' => 'Any 2-bedroom rentals?',
        'purpose' => 'rent',
    ])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Two listings match your search.')
        ->assertJsonPath('data.model', 'gpt-4o');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'http://ai.test/api/v1/property/ask'
            && ($data['question'] ?? null) === 'Any 2-bedroom rentals?'
            && ($data['purpose'] ?? null) === 'rent'
            && array_key_exists('properties', $data)
            && is_array($data['properties']);
    });
});

it('validates public property question payload', function (): void {
    $this->postJson('/api/public/ai/property/ask', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

it('returns a service error when the ai service is unavailable', function (): void {
    config([
        'ai.base_url' => 'http://ai.test',
    ]);

    Http::fake([
        'http://ai.test/api/v1/property/ask' => Http::response(null, 500),
    ]);

    $this->postJson('/api/public/ai/property/ask', [
        'question' => 'Hello',
        'purpose' => 'rent',
    ])
        ->assertStatus(502);
});

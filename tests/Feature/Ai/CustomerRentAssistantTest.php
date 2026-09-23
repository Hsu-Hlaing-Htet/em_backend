<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function aiCustomerUser(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

it('proxies a customer rent question with the sanctum token', function (): void {
    config([
        'ai.base_url' => 'http://ai.test',
    ]);

    $customer = aiCustomerUser();
    Sanctum::actingAs($customer);

    Http::fake([
        'http://ai.test/api/v1/rent/ask' => Http::response([
            'answer' => 'Your next invoice is due on the 1st.',
            'profile' => ['contracts' => []],
            'model' => 'gpt-4o',
        ], 200),
    ]);

    $token = $customer->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/customer/ai/rent/ask', [
            'question' => 'When is my next invoice due?',
        ])
        ->assertOk()
        ->assertJsonPath('data.answer', 'Your next invoice is due on the 1st.');

    Http::assertSent(function ($request) use ($token): bool {
        $data = $request->data();

        return $request->url() === 'http://ai.test/api/v1/rent/ask'
            && $request->hasHeader('Authorization', 'Bearer '.$token)
            && ($data['question'] ?? null) === 'When is my next invoice due?'
            && array_key_exists('profile', $data)
            && is_array($data['profile'] ?? null)
            && array_key_exists('contracts', $data['profile']);
    });
});

it('requires authentication for customer rent questions', function (): void {
    $this->postJson('/api/customer/ai/rent/ask', [
        'question' => 'Hello',
    ])->assertUnauthorized();
});

it('validates customer rent question payload', function (): void {
    $customer = aiCustomerUser();
    Sanctum::actingAs($customer);

    $this->postJson('/api/customer/ai/rent/ask', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['question']);
});

it('preloads authenticated customer contract context for the ai service', function (): void {
    config([
        'ai.base_url' => 'http://ai.test',
    ]);

    $customer = aiCustomerUser();
    Sanctum::actingAs($customer);

    Http::fake([
        'http://ai.test/api/v1/rent/ask' => Http::response([
            'answer' => 'Your active rent contract ends on 2027-01-22.',
            'profile' => ['contracts' => []],
            'model' => 'gpt-4o',
        ], 200),
    ]);

    $token = $customer->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/customer/ai/rent/ask', [
            'question' => 'When does my contract end?',
        ])
        ->assertOk();

    Http::assertSent(function ($request): bool {
        $profile = $request['profile'] ?? null;

        return is_array($profile)
            && array_key_exists('profile', $profile)
            && array_key_exists('contracts', $profile)
            && array_key_exists('invoices', $profile)
            && array_key_exists('payments', $profile)
            && array_key_exists('receipts', $profile)
            && array_key_exists('maintenance_requests', $profile)
            && array_key_exists('notifications', $profile)
            && array_key_exists('documents', $profile);
    });
});

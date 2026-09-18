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
        return $request->url() === 'http://ai.test/api/v1/rent/ask'
            && $request->hasHeader('Authorization', 'Bearer '.$token)
            && $request['question'] === 'When is my next invoice due?';
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

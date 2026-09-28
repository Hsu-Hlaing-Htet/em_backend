<?php

use App\Models\ContactInquiry;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    (new RoleSeeder)->run();
    (new UserSeeder)->run();
});

function contactInquiryAdmin(): User
{
    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function contactInquiryCustomer(): User
{
    return User::query()->where('email', 'mgmg@gmail.com')->firstOrFail();
}

function contactInquiryPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Aung Aung',
        'email' => 'aung@example.com',
        'phone' => '+95 9 123456789',
        'subject' => 'Property viewing request',
        'preferred_service' => 'Property Viewing',
        'message' => 'I would like to schedule a viewing this weekend.',
    ], $overrides);
}

it('accepts public contact form submissions', function (): void {
    $this->postJson('/api/public/contact', contactInquiryPayload())
        ->assertCreated()
        ->assertJsonPath('message', 'Your message has been sent successfully.');

    $this->assertDatabaseHas('contact_inquiries', [
        'email' => 'aung@example.com',
        'preferred_service' => 'Property Viewing',
        'status' => ContactInquiry::STATUS_NEW,
    ]);
});

it('validates public contact form submissions', function (): void {
    $this->postJson('/api/public/contact', [
        'name' => '',
        'email' => 'not-an-email',
        'phone' => '',
        'subject' => '',
        'preferred_service' => 'Unknown Service',
        'message' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'email',
            'phone',
            'subject',
            'preferred_service',
            'message',
        ]);
});

it('lists contact inquiries for admins only', function (): void {
    ContactInquiry::factory()->count(2)->create();
    ContactInquiry::factory()->read()->create();

    $this->getJson('/api/contact-inquiries')
        ->assertUnauthorized();

    $this->actingAs(contactInquiryCustomer(), 'sanctum')
        ->getJson('/api/contact-inquiries')
        ->assertForbidden();

    $this->actingAs(contactInquiryAdmin(), 'sanctum')
        ->getJson('/api/contact-inquiries')
        ->assertOk()
        ->assertJsonPath('data.total', 3)
        ->assertJsonCount(3, 'data.data');
});

it('marks an inquiry as read when an admin opens the detail', function (): void {
    $inquiry = ContactInquiry::factory()->create([
        'status' => ContactInquiry::STATUS_NEW,
        'read_at' => null,
    ]);

    $this->actingAs(contactInquiryAdmin(), 'sanctum')
        ->getJson("/api/contact-inquiries/{$inquiry->id}")
        ->assertOk()
        ->assertJsonPath('data.status', ContactInquiry::STATUS_READ)
        ->assertJsonPath('data.name', $inquiry->name)
        ->assertJsonPath('data.message', $inquiry->message);

    expect($inquiry->fresh()->status)->toBe(ContactInquiry::STATUS_READ);
    expect($inquiry->fresh()->read_at)->not->toBeNull();
});

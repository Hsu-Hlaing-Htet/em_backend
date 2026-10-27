<?php

use App\Models\Role;
use App\Models\User;
use App\Support\UserEmail;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function userEmailAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', UserEmail::SUPER_ADMIN_EMAIL)->firstOrFail();
}

function userEmailResidentPayload(string $email): array
{
    return [
        'name' => 'Email Policy Resident',
        'email' => $email,
        'phone' => '+95911112222',
        'nrc' => '12/ABC(N)123456',
        'dob' => '1990-01-15',
        'gender' => 'male',
        'address' => 'Yankin, Yangon',
    ];
}

test('resident creation accepts every valid provider and preserves format uniqueness and reserved ownership', function (string $email): void {
    $admin = userEmailAdmin();
    foreach (['' => UserEmail::MESSAGE_REQUIRED, 'not-an-email' => UserEmail::MESSAGE_INVALID] as $invalid => $message) {
        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/residents', userEmailResidentPayload($invalid))
            ->assertStatus(422)->assertJsonPath('data.email.0', $message);
    }
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload(UserEmail::SUPER_ADMIN_EMAIL))
        ->assertStatus(422)->assertJsonPath('data.email.0', UserEmail::MESSAGE_RESERVED);

    $normalized = UserEmail::normalize($email);
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload($email))
        ->assertCreated()->assertJsonPath('data.email', $normalized)
        ->assertJsonPath('data.role_name', Role::CUSTOMER);
    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload(strtoupper($normalized)))
        ->assertStatus(422)->assertJsonPath('data.email.0', UserEmail::MESSAGE_UNIQUE);
})->with(['new@gmail.com', 'new@yahoo.com', 'new@outlook.com', 'new@estate.example.org', '  NEW@ESTATE.EXAMPLE.ORG  ']);

test('resident email updates allow valid providers while rejecting duplicate and reserved addresses', function (string $email): void {
    $admin = userEmailAdmin();
    $created = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload('initial@example.org'))
        ->assertCreated()->json('data');
    $id = $created['id'];
    $this->actingAs($admin, 'sanctum')->putJson("/api/residents/{$id}", userEmailResidentPayload($created['email']))
        ->assertOk()->assertJsonPath('data.email', $created['email']);
    $this->actingAs($admin, 'sanctum')->putJson("/api/residents/{$id}", userEmailResidentPayload($email))
        ->assertOk()->assertJsonPath('data.email', UserEmail::normalize($email));
    $this->actingAs($admin, 'sanctum')->putJson("/api/residents/{$id}", userEmailResidentPayload('bad-format'))
        ->assertStatus(422)->assertJsonPath('data.email.0', UserEmail::MESSAGE_INVALID);
    $this->actingAs($admin, 'sanctum')->putJson("/api/residents/{$id}", userEmailResidentPayload(UserEmail::SUPER_ADMIN_EMAIL))
        ->assertStatus(422)->assertJsonPath('data.email.0', UserEmail::MESSAGE_RESERVED);

    $this->actingAs($admin, 'sanctum')->postJson('/api/residents', userEmailResidentPayload('occupied@example.org'))->assertCreated();
    $this->actingAs($admin, 'sanctum')->putJson("/api/residents/{$id}", userEmailResidentPayload('OCCUPIED@EXAMPLE.ORG'))
        ->assertStatus(422)->assertJsonPath('data.email.0', UserEmail::MESSAGE_UNIQUE);
})->with(['updated@gmail.com', 'updated@yahoo.com', 'updated@outlook.com', ' UPDATED@ESTATE.EXAMPLE.ORG ']);

test('normal resident update rejects password changes', function (): void {
    $admin = userEmailAdmin();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload('no.password.update.'.uniqid().'@gmail.com'))
        ->assertCreated();

    $residentId = $create->json('data.id');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/residents/{$residentId}", [
            ...userEmailResidentPayload($create->json('data.email')),
            'password' => 'New-direct-password-99',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password'], 'data');
});

test('normal staff update rejects password changes', function (): void {
    $admin = userEmailAdmin();
    $staff = User::query()->where('email', 'aungaung@gmail.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/staff/{$staff->id}", [
            'role_id' => $staff->role_id,
            'name' => $staff->name,
            'email' => $staff->email,
            'phone' => $staff->profile?->phone ?? '+95900000000',
            'nrc' => $staff->profile?->nrc ?? '12/ABC(N)000000',
            'dob' => $staff->profile?->dob?->toDateString() ?? '1990-01-01',
            'gender' => $staff->profile?->gender ?? 'female',
            'address' => $staff->profile?->address ?? 'Yangon',
            'password' => 'New-direct-password-99',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['password'], 'data');
});

test('super admin can keep reserved email and other users cannot take it', function (): void {
    $admin = userEmailAdmin();

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/staff/{$admin->id}", [
            'role_id' => $admin->role_id,
            'name' => $admin->name,
            'email' => UserEmail::SUPER_ADMIN_EMAIL,
            'phone' => $admin->profile?->phone ?? '+95900000000',
            'nrc' => $admin->profile?->nrc ?? '12/ABC(N)000000',
            'dob' => $admin->profile?->dob?->toDateString() ?? '1985-01-01',
            'gender' => $admin->profile?->gender ?? 'male',
            'address' => $admin->profile?->address ?? 'Yangon',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', UserEmail::SUPER_ADMIN_EMAIL);

    $other = User::query()->where('email', 'aungaung@gmail.com')->firstOrFail();

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/staff/{$other->id}", [
            'role_id' => $other->role_id,
            'name' => $other->name,
            'email' => UserEmail::SUPER_ADMIN_EMAIL,
            'phone' => $other->profile?->phone ?? '+95900000000',
            'nrc' => $other->profile?->nrc ?? '12/ABC(N)000000',
            'dob' => $other->profile?->dob?->toDateString() ?? '1990-01-01',
            'gender' => $other->profile?->gender ?? 'female',
            'address' => $other->profile?->address ?? 'Yangon',
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_RESERVED);
});


test('staff account creation accepts non-Gmail email and preserves uniqueness', function (): void {
    $admin = userEmailAdmin();
    \Illuminate\Support\Facades\Mail::fake();
    $payload = [...userEmailResidentPayload(' STAFF@ESTATE.EXAMPLE.ORG '),
        'role_id' => Role::query()->where('name', Role::ADMIN)->value('id')];
    $this->actingAs($admin, 'sanctum')->postJson('/api/staff', $payload)
        ->assertCreated()->assertJsonPath('data.email', 'staff@estate.example.org');
    $this->actingAs($admin, 'sanctum')->postJson('/api/staff', $payload)
        ->assertStatus(422)->assertJsonPath('data.email.0', UserEmail::MESSAGE_UNIQUE);
});

test('customer self-service email changes enforce provider-neutral validation and ownership', function (string $email): void {
    userEmailAdmin();
    $customer = User::query()->whereHas('role', fn ($q) => $q->where('name', Role::CUSTOMER))->firstOrFail();
    $other = User::query()->whereHas('role', fn ($q) => $q->where('name', Role::CUSTOMER))->whereKeyNot($customer->id)->firstOrFail();
    $otherEmail = $other->email;
    $this->actingAs($customer, 'sanctum')->putJson('/api/customer/profile', userEmailResidentPayload($email))
        ->assertOk()->assertJsonPath('data.email', UserEmail::normalize($email));
    $this->actingAs($customer, 'sanctum')->putJson('/api/customer/profile', userEmailResidentPayload(strtoupper($otherEmail)))
        ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    $this->actingAs($customer, 'sanctum')->putJson('/api/customer/profile', userEmailResidentPayload(UserEmail::SUPER_ADMIN_EMAIL))
        ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    $this->actingAs($customer, 'sanctum')->putJson('/api/customer/profile', userEmailResidentPayload('invalid'))
        ->assertUnprocessable()->assertJsonValidationErrors(['email']);
    expect($other->fresh()->email)->toBe($otherEmail);
})->with(['profile@gmail.com', 'profile@yahoo.com', 'profile@outlook.com', ' PROFILE@ESTATE.EXAMPLE.ORG ']);

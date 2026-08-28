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

test('resident create requires a unique gmail address', function (): void {
    $admin = userEmailAdmin();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload(''))
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_REQUIRED);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload('not-an-email'))
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_INVALID);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload('user@yahoo.com'))
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_GMAIL);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload(UserEmail::SUPER_ADMIN_EMAIL))
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_GMAIL);

    $email = 'policy.'.uniqid().'@gmail.com';

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload($email))
        ->assertCreated()
        ->assertJsonPath('data.email', $email)
        ->assertJsonPath('data.role_name', Role::CUSTOMER);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload(strtoupper($email)))
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_UNIQUE);
});

test('resident update allows keeping current email and rejects reserved or non-gmail changes', function (): void {
    $admin = userEmailAdmin();

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', userEmailResidentPayload('keep.'.uniqid().'@gmail.com'))
        ->assertCreated();

    $residentId = $create->json('data.id');
    $currentEmail = $create->json('data.email');

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/residents/{$residentId}", [
            ...userEmailResidentPayload($currentEmail),
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.email', $currentEmail);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/residents/{$residentId}", [
            ...userEmailResidentPayload('changed.'.uniqid().'@outlook.com'),
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_GMAIL);

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/residents/{$residentId}", [
            ...userEmailResidentPayload(UserEmail::SUPER_ADMIN_EMAIL),
        ])
        ->assertStatus(422)
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_GMAIL);

    $newEmail = 'changed.'.uniqid().'@gmail.com';

    $this->actingAs($admin, 'sanctum')
        ->putJson("/api/residents/{$residentId}", [
            ...userEmailResidentPayload($newEmail),
        ])
        ->assertOk()
        ->assertJsonPath('data.email', $newEmail);
});

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
        ->assertJsonPath('data.email.0', UserEmail::MESSAGE_GMAIL);
});

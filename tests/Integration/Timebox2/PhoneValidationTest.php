<?php

use App\Models\Role;
use App\Models\User;
use App\Support\PhoneNumber;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function phoneValidationAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', 'admin@rosewoodroyale.com')->firstOrFail();
}

function phoneValidationResidentPayload(string $phone): array
{
    return [
        'name' => 'Phone Validation Resident',
        'email' => 'phone.validation.'.uniqid().'@gmail.com',
        'phone' => $phone,
        'nrc' => '12/YaKaNa(N)123456',
        'dob' => '1990-01-15',
        'gender' => 'male',
        'address' => 'Yankin, Yangon',
    ];
}

test('resident phone validation accepts only plus 95 and exactly 9 local digits', function (): void {
    $admin = phoneValidationAdmin();

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', phoneValidationResidentPayload(''))
        ->assertStatus(422)
        ->assertJsonPath('data.phone.0', PhoneNumber::REQUIRED_MESSAGE);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', phoneValidationResidentPayload('+9591234567'))
        ->assertStatus(422)
        ->assertJsonPath('data.phone.0', PhoneNumber::INVALID_MESSAGE);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', phoneValidationResidentPayload('+959123456789'))
        ->assertStatus(422)
        ->assertJsonPath('data.phone.0', PhoneNumber::INVALID_MESSAGE);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', phoneValidationResidentPayload('+9591234a678'))
        ->assertStatus(422)
        ->assertJsonPath('data.phone.0', PhoneNumber::INVALID_MESSAGE);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', phoneValidationResidentPayload('+95912345678'))
        ->assertCreated()
        ->assertJsonPath('data.phone', '+95912345678');
});

test('staff phone validation reuses the same plus 95 rule', function (): void {
    $admin = phoneValidationAdmin();
    $roleId = Role::query()->where('name', Role::ADMIN)->value('id');

    $payload = [
        'role_id' => $roleId,
        'name' => 'Phone Validation Staff',
        'email' => 'staff.phone.validation.'.uniqid().'@gmail.com',
        'phone' => '+9591234567',
        'nrc' => '12/BaKaTa(N)654321',
        'dob' => '1988-05-20',
        'gender' => 'female',
        'address' => 'Bahan, Yangon',
    ];

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/staff', $payload)
        ->assertStatus(422)
        ->assertJsonPath('data.phone.0', PhoneNumber::INVALID_MESSAGE);

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/staff', [
            ...$payload,
            'email' => 'staff.phone.valid.'.uniqid().'@gmail.com',
            'phone' => '+95987654321',
        ])
        ->assertCreated()
        ->assertJsonPath('data.phone', '+95987654321');
});

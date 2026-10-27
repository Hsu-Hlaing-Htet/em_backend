<?php

use App\Models\Role;
use App\Models\User;
use App\Notifications\WelcomeAccountNotification;
use App\Support\TemporaryPassword;
use App\Support\UserEmail;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

function accountOnboardingAdmin(): User
{
    (new RoleSeeder)->run();
    (new UserSeeder)->run();

    return User::query()->where('email', UserEmail::SUPER_ADMIN_EMAIL)->firstOrFail();
}

function accountOnboardingResidentPayload(string $email): array
{
    return [
        'name' => 'Onboarding Resident',
        'email' => $email,
        'phone' => '+95911112222',
        'nrc' => '12/YaKaNa(N)123456',
        'dob' => '1990-01-15',
        'gender' => 'male',
        'address' => 'Yankin, Yangon',
    ];
}

test('creating a resident uses the fixed temporary password and requires first-login change', function (): void {
    Notification::fake();

    $admin = accountOnboardingAdmin();
    $email = 'onboard.'.uniqid().'@gmail.com';

    $create = $this->actingAs($admin, 'sanctum')
        ->postJson('/api/residents', accountOnboardingResidentPayload($email))
        ->assertCreated()
        ->assertJsonMissingPath('data.password')
        ->assertJsonPath('data.email', $email);

    $this->app['auth']->forgetGuards();

    $user = User::query()->where('email', $email)->firstOrFail();

    expect($user->must_change_password)->toBeTrue()
        ->and(Hash::check('password123', $user->password))->toBeFalse()
        ->and($user->password)->not->toBe(TemporaryPassword::VALUE)
        ->and(Hash::check(TemporaryPassword::VALUE, $user->password))->toBeTrue();

    Notification::assertSentTo($user, WelcomeAccountNotification::class);

    $plain = null;
    Notification::assertSentTo(
        $user,
        WelcomeAccountNotification::class,
        function (WelcomeAccountNotification $notification) use (&$plain, $user): bool {
            $property = (new \ReflectionClass($notification))->getProperty('temporaryPassword');
            $property->setAccessible(true);
            $plain = $property->getValue($notification);
            $mail = $notification->toMail($user);
            $html = $mail->render();

            return is_string($plain)
                && $plain === TemporaryPassword::VALUE
                && str_ends_with((string) $mail->actionUrl, '/login')
                && ! str_contains($html, '<img')
                && ! str_contains($html, 'Login to Rosewood Royale')
                && str_contains($html, 'Customer Portal Login')
                && str_contains($html, e((string) $user->email))
                && str_contains($html, e(TemporaryPassword::VALUE));
        },
    );

    expect($plain)->toBe(TemporaryPassword::VALUE)
        ->and(Hash::check((string) $plain, $user->password))->toBeTrue();

    $login = $this->postJson('/api/auth/login', [
        'email' => $email,
        'password' => TemporaryPassword::VALUE,
    ])
        ->assertOk()
        ->assertJsonPath('user.must_change_password', true)
        ->assertJsonMissingPath('user.password');

    $token = $login->json('token');

    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->postJson('/api/auth/change-password', [
            'current_password' => TemporaryPassword::VALUE,
            'password' => 'Brand-new-pass-99',
            'password_confirmation' => 'Brand-new-pass-99',
        ])
        ->assertOk();

    $user->refresh();

    expect($user->must_change_password)->toBeFalse()
        ->and(Hash::check('Brand-new-pass-99', $user->password))->toBeTrue()
        ->and(Hash::check(TemporaryPassword::VALUE, $user->password))->toBeFalse();

    $this->postJson('/api/auth/login', [
        'email' => $email,
        'password' => TemporaryPassword::VALUE,
    ])
        ->assertStatus(422);

    $this->postJson('/api/auth/login', [
        'email' => $email,
        'password' => 'Brand-new-pass-99',
    ])
        ->assertOk()
        ->assertJsonPath('user.must_change_password', false);
});

test('creating staff rejects client-supplied passwords and emails a temporary password', function (): void {
    Notification::fake();

    $admin = accountOnboardingAdmin();
    $roleId = Role::query()->where('name', Role::ADMIN)->value('id');
    $email = 'staff.onboard.'.uniqid().'@gmail.com';

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/staff', [
            'role_id' => $roleId,
            'name' => 'Onboarding Staff',
            'email' => $email,
            'password' => 'client-supplied-password',
            'phone' => '+95933334444',
            'nrc' => '12/BaKaTa(N)654321',
            'dob' => '1988-05-20',
            'gender' => 'female',
            'address' => 'Bahan, Yangon',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password'], 'data');

    $this->actingAs($admin, 'sanctum')
        ->postJson('/api/staff', [
            'role_id' => $roleId,
            'name' => 'Onboarding Staff',
            'email' => $email,
            'phone' => '+95933334444',
            'nrc' => '12/BaKaTa(N)654321',
            'dob' => '1988-05-20',
            'gender' => 'female',
            'address' => 'Bahan, Yangon',
        ])
        ->assertCreated();

    $user = User::query()->where('email', $email)->firstOrFail();

    expect($user->must_change_password)->toBeTrue()
        ->and($user->password)->not->toBe(TemporaryPassword::VALUE)
        ->and(Hash::check(TemporaryPassword::VALUE, $user->password))->toBeTrue();

    Notification::assertSentTo(
        $user,
        WelcomeAccountNotification::class,
        function (WelcomeAccountNotification $notification) use ($user): bool {
            $property = (new \ReflectionClass($notification))->getProperty('temporaryPassword');
            $property->setAccessible(true);
            $mail = $notification->toMail($user);

            return $property->getValue($notification) === TemporaryPassword::VALUE
                && str_ends_with((string) $mail->actionUrl, '/login');
        },
    );
});

test('seeded super admin does not require a password change', function (): void {
    $admin = accountOnboardingAdmin();

    expect($admin->must_change_password)->toBeFalse();

    $this->postJson('/api/auth/login', [
        'email' => $admin->email,
        'password' => 'p@ssword',
    ])
        ->assertOk()
        ->assertJsonPath('user.must_change_password', false);
});

<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Support\MyanmarSampleData;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * @var list<array{role: string, name: string, email: string, password: string}>
     */
    private static array $credentials = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        self::$credentials = [];

        $superAdminRole = Role::findByName(Role::SUPER_ADMIN);
        $adminRole = Role::findByName(Role::ADMIN);
        $customerRole = Role::findByName(Role::CUSTOMER);

        if (! $superAdminRole || ! $adminRole || ! $customerRole) {
            $this->command?->error('Roles must be seeded before users. Run RoleSeeder first.');

            return;
        }

        $password = 'p@ssword';

        $this->createUserWithProfile(
            $superAdminRole->id,
            'U Kyaw Swar',
            'admin@rosewoodroyale.com',
            $password,
            [
                'phone' => '+95 9 420 123456',
                'nrc' => '12/YaKaNa(N)123456',
                'dob' => '1985-03-15',
                'gender' => 'male',
                'address' => 'No. 1, Pyay Road, Kamayut Township, Yangon',
            ]
        );

        $this->createUserWithProfile(
            $adminRole->id,
            'Daw Theingi',
            'aungaung@rosewoodroyale.com',
            $password,
            [
                'phone' => '+95 9 421 234567',
                'nrc' => '12/BaKaTa(N)234567',
                'dob' => '1990-07-22',
                'gender' => 'female',
                'address' => 'No. 25, Inya Road, Bahan Township, Yangon',
            ]
        );

        foreach (MyanmarSampleData::customers() as $customer) {
            $this->createUserWithProfile(
                $customerRole->id,
                $customer['name'],
                $customer['email'],
                $password,
                [
                    'phone' => $customer['phone'],
                    'nrc' => $customer['nrc'],
                    'dob' => $customer['dob'],
                    'gender' => $customer['gender'],
                    'address' => $customer['address'],
                ]
            );
        }
    }

    /**
     * @return list<array{role: string, name: string, email: string, password: string}>
     */
    public static function credentials(): array
    {
        return self::$credentials;
    }

    /**
     * @param  array{phone: string, nrc: string, dob: string, gender: string, address: string}  $profileData
     */
    private function createUserWithProfile(
        int $roleId,
        string $name,
        string $email,
        string $password,
        array $profileData
    ): void {
        $user = User::query()->create([
            'role_id' => $roleId,
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        Profile::query()->create([
            'user_id' => $user->id,
            ...$profileData,
            'avatar_path' => null,
        ]);

        $this->recordCredentials($user, $password);
    }

    private function recordCredentials(User $user, string $password = 'p@ssword'): void
    {
        $user->loadMissing('role');

        self::$credentials[] = [
            'role' => $user->role?->name ?? 'unknown',
            'name' => $user->name,
            'email' => $user->email,
            'password' => $password,
        ];
    }
}

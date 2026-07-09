<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
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
            'Super Admin',
            'admin@rosewoodroyale.com',
            $password,
            [
                'phone' => '+95 9 420 123456',
                'nrc' => '12/YaKaNa(N)123456',
                'dob' => '1985-03-15',
                'gender' => 'male',
                'address' => 'No. 1, Pyay Road, Yangon, Myanmar',
            ]
        );

        $this->createUserWithProfile(
            $adminRole->id,
            'Aung Aung',
            'aungaung@rosewoodroyale.com',
            $password,
            [
                'phone' => '+95 9 421 234567',
                'nrc' => '12/BaKaTa(N)234567',
                'dob' => '1990-07-22',
                'gender' => 'male',
                'address' => 'No. 25, Inya Road, Yangon, Myanmar',
            ]
        );

        $this->createUserWithProfile(
            $customerRole->id,
            'Mg Mg',
            'mgmg@rosewoodroyale.com',
            $password,
            [
                'phone' => '+95 9 422 345678',
                'nrc' => '12/LaKaNa(N)345678',
                'dob' => '1995-11-08',
                'gender' => 'male',
                'address' => 'No. 10, Bahan Township, Yangon, Myanmar',
            ]
        );

        $additionalAdmins = [
            ['name' => 'Su Su', 'email' => 'susu@rosewoodroyale.com'],
            ['name' => 'Hla Hla', 'email' => 'hlahla@rosewoodroyale.com'],
        ];

        foreach ($additionalAdmins as $index => $admin) {
            $this->createUserWithProfile(
                $adminRole->id,
                $admin['name'],
                $admin['email'],
                $password,
                [
                    'phone' => '+95 9 423 '.str_pad((string) (456789 + $index), 6, '0', STR_PAD_LEFT),
                    'nrc' => '12/MaNyaTa(N)'.str_pad((string) (456789 + $index), 6, '0', STR_PAD_LEFT),
                    'dob' => '1988-0'.($index + 1).'-10',
                    'gender' => 'female',
                    'address' => 'No. '.($index + 30).', Dagon Township, Yangon, Myanmar',
                ]
            );
        }

        $additionalCustomers = [
            ['name' => 'Ko Ko', 'email' => 'koko@rosewoodroyale.com'],
            ['name' => 'Ma Ma', 'email' => 'mama@rosewoodroyale.com'],
            ['name' => 'Nyi Nyi', 'email' => 'nyinyi@rosewoodroyale.com'],
            ['name' => 'Phyu Phyu', 'email' => 'phyuphyu@rosewoodroyale.com'],
            ['name' => 'Zaw Zaw', 'email' => 'zawzaw@rosewoodroyale.com'],
        ];

        foreach ($additionalCustomers as $index => $customer) {
            $this->createUserWithProfile(
                $customerRole->id,
                $customer['name'],
                $customer['email'],
                $password,
                [
                    'phone' => '+95 9 424 '.str_pad((string) (567890 + $index), 6, '0', STR_PAD_LEFT),
                    'nrc' => '12/YaKaNa(N)'.str_pad((string) (567890 + $index), 6, '0', STR_PAD_LEFT),
                    'dob' => '199'.($index + 2).'-05-'.str_pad((string) ($index + 10), 2, '0', STR_PAD_LEFT),
                    'gender' => $index % 2 === 0 ? 'male' : 'female',
                    'address' => 'No. '.($index + 50).', Kamayut Township, Yangon, Myanmar',
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

        $this->recordCredentials($user);
    }

    private function recordCredentials(User $user): void
    {
        $user->loadMissing('role');

        self::$credentials[] = [
            'role' => $user->role?->name ?? 'unknown',
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'p@ssword',
        ];
    }
}

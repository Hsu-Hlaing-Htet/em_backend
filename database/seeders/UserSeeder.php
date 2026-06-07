<?php

namespace Database\Seeders;

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

        if (! Role::findByName(Role::SUPER_ADMIN) || ! Role::findByName(Role::ADMIN) || ! Role::findByName(Role::CUSTOMER)) {
            $this->command?->error('Roles must be seeded before users. Run RoleSeeder first.');

            return;
        }

        $this->recordCredentials(
            User::factory()
                ->superAdmin()
                ->withProfile()
                ->create([
                    'name' => 'Super Admin',
                    'email' => 'admin@rosewoodroyale.com',
                    'password' => 'p@ssword',
                ])
        );
        
        $this->recordCredentials(
            User::factory()
                ->admin()
                ->withProfile()
                ->create([
                    'name' => 'Aung Aung',
                    'email' => 'aungaung@rosewoodroyale.com',
                    'password' => 'p@ssword',
                ])
        );
        
        $this->recordCredentials(
            User::factory()
                ->customer()
                ->withProfile()
                ->create([
                    'name' => 'Mg Mg',
                    'email' => 'mgmg@rosewoodroyale.com',
                    'password' => 'p@ssword',
                ])
        );

        User::factory()
        ->admin()
        ->withProfile()
        ->count(2)
        ->create()
        ->each(fn (User $admin) => $this->recordCredentials($admin));
        
        User::factory()
            ->customer()
            ->withProfile()
            ->count(5)
            ->create()
            ->each(fn (User $user) => $this->recordCredentials($user));
    }

    /**
     * @return list<array{role: string, name: string, email: string, password: string}>
     */
    public static function credentials(): array
    {
        return self::$credentials;
    }

    private function recordCredentials(User $user): void
    {
        $user->loadMissing('role');

        self::$credentials[] = [
            'role' => $user->role()->value('name') ?? 'unknown',
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'p@ssword',
        ];
    }
}

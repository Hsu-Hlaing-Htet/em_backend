<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\Support\MyanmarSampleData;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customers = MyanmarSampleData::customers();
        $customer = fake()->randomElement($customers);
        $localPart = strtolower(str_replace([' ', '.'], '', explode('@', $customer['email'])[0]));

        return [
            'role_id' => $this->roleId(Role::CUSTOMER),
            'name' => $customer['name'],
            'email' => $localPart.'.'.fake()->unique()->numerify('##').'@rosewoodroyale.com',
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(Role::SUPER_ADMIN),
            'name' => 'U Kyaw Swar',
            'email' => 'kyawswar@rosewoodroyale.com',
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(Role::ADMIN),
            'name' => 'Daw Theingi',
            'email' => 'theingi@rosewoodroyale.com',
        ]);
    }

    public function customer(): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(Role::CUSTOMER),
        ]);
    }

    public function withProfile(): static
    {
        return $this->afterCreating(function (User $user): void {
            Profile::factory()->create([
                'user_id' => $user->id,
            ]);
        });
    }

    protected function roleId(string $roleName): int
    {
        $roleId = Role::findByName($roleName)?->id;

        if ($roleId === null) {
            throw new \RuntimeException("Role [{$roleName}] not found. Run RoleSeeder before using UserFactory.");
        }

        return $roleId;
    }
}

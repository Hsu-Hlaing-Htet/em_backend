<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Role;
use App\Models\User;
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
    $name = fake()->randomElement([
        'Aung Myat Kyaw',
        'Thant Zin Oo',
        'Min Khant Ko',
        'Ye Yint Aung',
        'Soe Min Htet',
        'Nay Lin Tun',
        'Hsu Hlaing Htet',
        'Ei Mon Khaing',
        'Thiri Shwe Sin',
        'Yu Waddy Phyo',
        'May Thazin Tun',
        'Hnin Wut Yi',
        'Moe Pwint Phyu',
        'Su Myat Noe',
        'Khin Thiri Aung',
    ]);

    return [
        'role_id' => $this->roleId(Role::CUSTOMER),
        'name' => $name,
        'email' => strtolower(str_replace(' ', '', $name)) . rand(1, 99) . '@rosewoodroyale.com',
        'password' => static::$password ??= Hash::make('password'),
    ];
}

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(Role::SUPER_ADMIN),
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role_id' => $this->roleId(Role::ADMIN),
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

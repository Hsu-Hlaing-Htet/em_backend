<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            Role::SUPER_ADMIN,
            Role::ADMIN,
            Role::CUSTOMER,
        ];

        foreach ($roles as $roleName) {
            Role::updateOrCreate([
                'name' => $roleName,
            ]);
        }
    }
}

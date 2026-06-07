<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([Role::SUPER_ADMIN, Role::ADMIN, Role::CUSTOMER] as $roleName) {
            Role::updateOrCreate(['name' => $roleName]);
        }
    }
}

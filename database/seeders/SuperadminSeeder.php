<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $roleId = Role::query()->where('code', Role::CODE_SUPERADMIN)->value('id');
        if ($roleId === null) {
            return;
        }

        User::updateOrCreate(
            ['username' => 'lumierra'],
            [
                'name' => 'Lumierra',
                'email' => null,
                'phone' => null,
                'password' => 'lumierra', // hashed via User::$casts['password' => 'hashed']
                'role_id' => $roleId,
                'is_active' => true,
                'force_password_change' => false,
                'password_changed_at' => now(),
            ],
        );
    }
}

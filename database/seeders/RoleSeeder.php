<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'code' => Role::CODE_SUPERADMIN,
                'name' => 'Superadmin',
                'description' => 'Akses penuh seluruh sistem (bypass permission matrix).',
                'sort_order' => 1,
            ],
            [
                'code' => Role::CODE_ADMIN,
                'name' => 'Admin',
                'description' => 'Operasional manajemen sehari-hari di luar setting sensitif.',
                'sort_order' => 2,
            ],
            [
                'code' => Role::CODE_KASIR,
                'name' => 'Kasir',
                'description' => 'Pembayaran customer, payment request, AR aging.',
                'sort_order' => 3,
            ],
            [
                'code' => Role::CODE_OPERATOR,
                'name' => 'Operator Gudang',
                'description' => 'GRN, stok, adjustment, opname, surat jalan.',
                'sort_order' => 4,
            ],
            [
                'code' => Role::CODE_SALES,
                'name' => 'Sales',
                'description' => 'Visit, sales order, retur, mostly via aplikasi mobile.',
                'sort_order' => 5,
            ],
        ];

        foreach ($roles as $row) {
            Role::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, ['is_system' => true, 'is_active' => true]),
            );
        }
    }
}

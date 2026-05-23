<?php

namespace Database\Seeders;

use App\Models\PriceTier;
use Illuminate\Database\Seeder;

class PriceTierSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'code' => PriceTier::CODE_GROSIR,
                'name' => 'Grosir',
                'description' => 'Harga grosir untuk toko/pedagang besar.',
                'sort_order' => 10,
            ],
            [
                'code' => PriceTier::CODE_ECERAN,
                'name' => 'Eceran',
                'description' => 'Harga eceran retail untuk customer akhir.',
                'sort_order' => 20,
            ],
            [
                'code' => PriceTier::CODE_MODERN,
                'name' => 'Modern Trade',
                'description' => 'Harga untuk minimarket/supermarket.',
                'sort_order' => 30,
            ],
        ];

        foreach ($rows as $row) {
            PriceTier::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'is_system' => true,
                    'is_active' => true,
                ]),
            );
        }
    }
}

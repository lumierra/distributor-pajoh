<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use Illuminate\Database\Seeder;

class CustomerTypeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'WARUNG', 'name' => 'Warung Tradisional', 'sort_order' => 1],
            ['code' => 'KELONTONG', 'name' => 'Toko Kelontong', 'sort_order' => 2],
            ['code' => 'MINI_MARKET', 'name' => 'Mini Market', 'sort_order' => 3],
            ['code' => 'SUPERMARKET', 'name' => 'Supermarket', 'sort_order' => 4],
            ['code' => 'GROSIR', 'name' => 'Toko Grosir', 'sort_order' => 5],
            ['code' => 'RESTORAN', 'name' => 'Restoran / Café', 'sort_order' => 6],
            ['code' => 'HOTEL', 'name' => 'Hotel / Catering', 'sort_order' => 7],
            ['code' => 'KANTIN', 'name' => 'Kantin / Sekolah', 'sort_order' => 8],
            ['code' => 'LAINNYA', 'name' => 'Lainnya', 'sort_order' => 99],
        ];

        foreach ($rows as $row) {
            CustomerType::updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true],
            );
        }
    }
}

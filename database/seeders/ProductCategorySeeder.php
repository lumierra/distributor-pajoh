<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'FMC', 'name' => 'FMCG', 'description' => 'Fast Moving Consumer Goods'],
            ['code' => 'MIE', 'name' => 'Mie & Pasta', 'description' => 'Mie instan, pasta'],
            ['code' => 'SNK', 'name' => 'Snack & Kembang Gula', 'description' => 'Biskuit, candy, chips'],
            ['code' => 'BMB', 'name' => 'Bumbu & Bahan Pokok', 'description' => 'Garam, gula, bumbu masak'],
            ['code' => 'MIN', 'name' => 'Minuman', 'description' => 'Minuman ringan, kopi, teh'],
            ['code' => 'PRC', 'name' => 'Personal Care', 'description' => 'Sabun, shampo, pasta gigi'],
            ['code' => 'RMT', 'name' => 'Rumah Tangga', 'description' => 'Deterjen, peralatan rumah'],
            ['code' => 'LNY', 'name' => 'Lainnya', 'description' => 'Kategori umum'],
        ];

        foreach ($rows as $i => $row) {
            ProductCategory::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'is_active' => true,
                    'sort_order' => ($i + 1) * 10,
                ]),
            );
        }
    }
}

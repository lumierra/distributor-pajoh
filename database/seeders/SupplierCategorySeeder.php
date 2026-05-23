<?php

namespace Database\Seeders;

use App\Models\SupplierCategory;
use Illuminate\Database\Seeder;

class SupplierCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'FMCG', 'name' => 'FMCG', 'description' => 'Fast Moving Consumer Goods (umum)'],
            ['code' => 'MAKANAN', 'name' => 'Makanan & Minuman', 'description' => 'Produk pangan & beverage'],
            ['code' => 'SNACK', 'name' => 'Snack & Kembang Gula', 'description' => 'Camilan, biscuit, candy'],
            ['code' => 'KEMASAN', 'name' => 'Kemasan', 'description' => 'Packaging, kantong plastik, kardus'],
            ['code' => 'PERSONAL', 'name' => 'Personal Care', 'description' => 'Sabun, pasta gigi, kosmetik'],
            ['code' => 'RUMAH', 'name' => 'Rumah Tangga', 'description' => 'Cleaning, deterjen, peralatan rumah'],
            ['code' => 'LAINNYA', 'name' => 'Lainnya', 'description' => 'Kategori umum (catch-all)'],
        ];

        foreach ($rows as $i => $row) {
            SupplierCategory::updateOrCreate(
                ['code' => $row['code']],
                array_merge($row, [
                    'is_active' => true,
                    'sort_order' => ($i + 1) * 10,
                ]),
            );
        }
    }
}

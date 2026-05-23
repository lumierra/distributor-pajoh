<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            RoleSeeder::class,
            MenuSeeder::class,
            RolePermissionSeeder::class,
            SuperadminSeeder::class,
            // Fase 2 — Master Data
            SupplierCategorySeeder::class,
            ProductCategorySeeder::class,
            PriceTierSeeder::class,
            CustomerTypeSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = ['PCS', 'KARDUS', 'KRAT', 'PAK', 'LUSIN', 'KG', 'LITER', 'BOX', 'SAK'];

        foreach ($defaults as $name) {
            Unit::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}

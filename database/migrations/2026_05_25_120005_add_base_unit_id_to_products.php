<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah FK `base_unit_id` ke `products` (referensi ke `product_units`).
 * Dipisah dari migration create_products supaya tidak terjadi circular FK
 * (product_units.product_id → products.id, products.base_unit_id → product_units.id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('base_unit_id')->nullable()->after('category_id')
                ->constrained('product_units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('base_unit_id');
        });
    }
};

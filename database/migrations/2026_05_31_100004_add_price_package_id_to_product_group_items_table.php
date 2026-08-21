<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saat produk di-assign ke Product Group, admin pilih paket harga mana
 * yang berlaku untuk group itu. Nullable: kalau null, sales pakai paket
 * default produk (paket pertama / is_active).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_group_items', function (Blueprint $table): void {
            $table->foreignId('price_package_id')->nullable()->after('product_id')
                ->constrained('product_price_packages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('product_group_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_package_id');
        });
    }
};

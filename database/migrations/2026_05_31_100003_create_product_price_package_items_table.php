<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris di dalam paket harga: satu satuan produk (product_unit) → harga
 * modal + harga jual. Satuan yang sama boleh muncul di paket berbeda
 * dengan harga berbeda, tapi tidak boleh dobel dalam satu paket.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_package_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('price_package_id')->constrained('product_price_packages')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->cascadeOnDelete();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['price_package_id', 'product_unit_id'], 'pppi_unique');
            $table->index('product_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_package_items');
    }
};

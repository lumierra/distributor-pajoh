<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket harga per customer: customer ini untuk produk tertentu pakai paket harga
 * mana. Dipakai di SalesOrderService::resolveSellPrice sebagai tier paling atas
 * (di atas sales-group & paket pertama). Produk yang tidak di-set jatuh ke
 * fallback tsb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_price_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            // Paket harga (dari product_price_packages). Kalau paket dihapus → row ikut terhapus.
            $table->foreignId('price_package_id')->constrained('product_price_packages')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // 1 baris per (customer, produk).
            $table->unique(['customer_id', 'product_id'], 'cppp_unique');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_price_packages');
    }
};

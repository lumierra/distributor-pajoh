<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket harga bernama per produk. Satu produk punya ≥1 paket (mis.
 * "Harga Reguler", "Harga Grosir"). Paket di-assign ke sales lewat
 * Product Group. Baris harga (satuan → modal + jual) ada di tabel
 * product_price_package_items.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 128);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_packages');
    }
};

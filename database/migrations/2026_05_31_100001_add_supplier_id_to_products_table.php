<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 produk = 1 supplier. Sebelumnya supplier↔produk adalah pivot M2M
 * (supplier_products, di-drop di migration terpisah). Sekarang supplier
 * jadi FK langsung di products.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('id')
                ->constrained('suppliers')->restrictOnDelete();
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};

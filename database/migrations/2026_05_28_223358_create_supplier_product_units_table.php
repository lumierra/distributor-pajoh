<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot harga per (supplier × produk × satuan). Sumber kebenaran cost & sell
 * price untuk SO. Tiap baris menyimpan kombinasi distinct.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->cascadeOnDelete();
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['supplier_id', 'product_id', 'product_unit_id'], 'spu_unique');
            $table->index('product_id');
            $table->index('product_unit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_units');
    }
};

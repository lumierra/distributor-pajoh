<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained('supplier_returns')->cascadeOnDelete();

            $table->string('source_type', 32)->index();
            $table->foreignId('source_id')->nullable();
            $table->foreignId('grn_item_id')->nullable()->constrained('grn_items')->nullOnDelete();
            $table->foreignId('customer_return_item_id')->nullable()->constrained('customer_return_items')->nullOnDelete();
            // adjustment FK deferred — kolom tetap ada untuk forward-compat
            $table->foreignId('stock_adjustment_item_id')->nullable();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('product_sku_snapshot', 64);
            $table->string('product_unit_name_snapshot', 32);
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->string('batch_code_snapshot', 64)->nullable();

            $table->unsignedInteger('qty');
            $table->unsignedBigInteger('qty_base')->default(0);

            $table->decimal('cost_price', 15, 2);
            $table->decimal('line_value', 15, 2);

            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();

            $table->foreignId('invoice_item_id')->nullable();
            $table->foreignId('do_item_id')->nullable();
            $table->foreignId('so_item_id')->nullable();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('product_sku_snapshot', 64);
            $table->string('product_unit_name_snapshot', 32);
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->string('batch_code_snapshot', 64)->nullable();

            $table->unsignedInteger('qty_total');
            $table->unsignedInteger('qty_good')->default(0);
            $table->unsignedInteger('qty_bs')->default(0);

            $table->unsignedBigInteger('qty_total_base')->default(0);
            $table->unsignedBigInteger('qty_good_base')->default(0);
            $table->unsignedBigInteger('qty_bs_base')->default(0);

            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_value', 15, 2);

            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('product_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_return_items');
    }
};

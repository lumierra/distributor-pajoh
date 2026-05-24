<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('sales_order_item_id')->constrained('so_items')->restrictOnDelete();
            $table->foreignId('do_item_id')->constrained('do_items')->restrictOnDelete();

            // Produk snapshot
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('product_sku_snapshot', 64);
            $table->string('product_unit_name_snapshot', 32);
            $table->string('batch_code_snapshot', 64)->nullable();

            // Quantity & pricing
            $table->unsignedInteger('qty');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_z1_pct', 5, 2)->default(0);
            $table->decimal('discount_z2_pct', 5, 2)->default(0);
            $table->decimal('unit_net_price', 15, 2);
            $table->decimal('line_subtotal', 15, 2);

            $table->boolean('is_bonus')->default(false);

            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('product_id');
            $table->index('sales_order_item_id');
            $table->index('do_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};

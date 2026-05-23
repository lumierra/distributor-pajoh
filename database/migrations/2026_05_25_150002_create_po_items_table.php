<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            // Produk
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('product_sku_snapshot', 64);
            $table->string('product_unit_name_snapshot', 32);

            // Quantity
            $table->unsignedInteger('qty_ordered');
            $table->unsignedInteger('qty_received')->default(0);
            $table->unsignedInteger('bonus_qty')->default(0);
            $table->unsignedInteger('bonus_qty_received')->default(0);

            // Harga
            $table->decimal('cost_price', 15, 2);
            $table->decimal('discount_z1_pct', 5, 2)->default(0);
            $table->decimal('discount_z2_pct', 5, 2)->default(0);
            $table->decimal('unit_net_cost', 15, 2);
            $table->decimal('line_subtotal', 15, 2);

            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_items');
    }
};

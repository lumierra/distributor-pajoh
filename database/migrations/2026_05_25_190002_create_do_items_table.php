<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('do_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->cascadeOnDelete();
            $table->foreignId('so_item_id')->constrained('so_items')->restrictOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('so_reservations')->nullOnDelete();

            // Produk
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('product_sku_snapshot', 64);
            $table->string('product_unit_name_snapshot', 32);

            // Batch
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->string('batch_code_snapshot', 64)->nullable();
            $table->date('expired_date_snapshot')->nullable();

            // Quantity (dalam UoM dipilih)
            $table->unsignedInteger('qty_planned');
            $table->unsignedInteger('qty_picked')->default(0);
            $table->unsignedInteger('qty_delivered')->default(0);
            $table->unsignedInteger('qty_returned')->default(0);

            $table->boolean('is_bonus')->default(false);

            // Base unit (computed)
            $table->unsignedBigInteger('qty_planned_base')->default(0);
            $table->unsignedBigInteger('qty_delivered_base')->default(0);
            $table->unsignedBigInteger('qty_returned_base')->default(0);

            // Cost (untuk HPP)
            $table->decimal('cost_price_base', 15, 4)->nullable();

            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('so_item_id');
            $table->index('product_id');
            $table->index('batch_id');
            $table->index('reservation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('do_items');
    }
};

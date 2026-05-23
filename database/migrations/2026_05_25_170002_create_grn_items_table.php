<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignId('po_item_id')->constrained('po_items')->restrictOnDelete();

            // Produk snapshot
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('product_name_snapshot', 255);
            $table->string('product_sku_snapshot', 64);
            $table->string('product_unit_name_snapshot', 32);

            // Batch
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->restrictOnDelete();
            $table->string('batch_code', 64);
            $table->date('production_date')->nullable();
            $table->date('expired_date')->nullable()->index();

            // Quantity (dalam UoM dipilih)
            $table->unsignedInteger('qty_reguler')->default(0);
            $table->unsignedInteger('qty_bonus')->default(0);
            $table->unsignedInteger('qty_damaged')->default(0);

            // Quantity base (computed saat posting)
            $table->unsignedBigInteger('qty_reguler_base')->default(0);
            $table->unsignedBigInteger('qty_bonus_base')->default(0);

            // Cost
            $table->decimal('cost_price', 15, 2);
            $table->decimal('cost_price_base', 15, 4);
            $table->boolean('cost_overridden')->default(false);
            $table->text('cost_override_reason')->nullable();

            // Kondisi & notes
            $table->string('condition', 16)->default('good');
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('po_item_id');
            $table->index('product_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
    }
};

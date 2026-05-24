<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('so_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->foreignId('so_item_id')->constrained('so_items')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->unsignedBigInteger('qty_reserved');
            $table->string('status', 16)->default('active')->index();
            $table->timestamp('consumed_at')->nullable();
            // consumed_by_do_id ditautkan ke delivery_orders (T12). Nullable, no FK dulu.
            $table->unsignedBigInteger('consumed_by_do_id')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->string('released_reason', 255)->nullable();
            $table->timestamps();

            $table->index('sales_order_id');
            $table->index('so_item_id');
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('so_reservations');
    }
};

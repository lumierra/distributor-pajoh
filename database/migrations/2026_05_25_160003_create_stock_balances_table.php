<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->bigInteger('qty_on_hand')->default(0);
            $table->bigInteger('qty_bonus_pool')->default(0);
            $table->unsignedBigInteger('qty_reserved')->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_id'], 'stock_balances_unique');
            $table->index('qty_on_hand');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};

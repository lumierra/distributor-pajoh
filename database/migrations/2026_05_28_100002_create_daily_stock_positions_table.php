<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_stock_positions', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();

            $table->unsignedBigInteger('qty_on_hand_base')->default(0);
            $table->unsignedBigInteger('qty_reserved_base')->default(0);
            $table->unsignedBigInteger('qty_bonus_pool_base')->default(0);
            $table->decimal('avg_cost', 15, 4)->nullable();
            $table->decimal('stock_value', 15, 2)->default(0);
            $table->integer('days_since_last_movement')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['snapshot_date', 'product_id', 'batch_id'], 'dsp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_stock_positions');
    }
};

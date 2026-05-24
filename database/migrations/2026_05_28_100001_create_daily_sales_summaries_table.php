<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_sales_summaries', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();

            $table->foreignId('sales_id')->nullable()->index();
            $table->foreignId('customer_id')->nullable()->index();
            $table->foreignId('product_id')->nullable()->index();
            $table->foreignId('category_id')->nullable();

            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedBigInteger('qty_sold_base')->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0);
            $table->decimal('margin', 15, 2)->default(0);
            $table->decimal('margin_percent', 7, 2)->default(0);

            $table->timestamp('created_at')->nullable();

            // Idempotent upsert key — gunakan composite unique
            $table->unique(['snapshot_date', 'sales_id', 'customer_id', 'product_id'], 'dss_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_summaries');
    }
};

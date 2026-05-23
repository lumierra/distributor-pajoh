<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_bulk_price_updates', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->json('filter_criteria')->nullable();
            $table->string('update_type', 16); // percentage / fixed_amount / fixed_value
            $table->decimal('update_value', 15, 4);
            $table->json('tier_ids');
            $table->json('unit_levels')->nullable();
            $table->integer('affected_count')->default(0);
            $table->string('status', 16)->default('pending'); // pending / applied / failed
            $table->timestamp('applied_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_bulk_price_updates');
    }
};

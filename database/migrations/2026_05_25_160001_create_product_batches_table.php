<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('batch_code', 64);
            $table->date('production_date')->nullable();
            $table->date('expired_date')->nullable()->index();
            $table->unsignedBigInteger('initial_qty_base')->default(0);
            $table->timestamp('first_received_at')->nullable();
            $table->timestamp('last_received_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Unique composite: product + supplier + batch_code + production_date.
            // MySQL TRUE-NULL semantics: rows dengan NULL pada salah satu field
            // tetap unique antar-satu sama lain. Cukup untuk MVP.
            $table->unique(['product_id', 'supplier_id', 'batch_code', 'production_date'], 'product_batches_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};

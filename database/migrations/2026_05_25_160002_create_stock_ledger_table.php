<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->foreignId('product_unit_id')->constrained('product_units')->restrictOnDelete();
            $table->string('type', 32)->index();
            $table->boolean('is_bonus_pool')->default(false);
            $table->unsignedBigInteger('qty_in')->default(0);
            $table->unsignedBigInteger('qty_out')->default(0);
            $table->decimal('cost_price', 15, 4)->default(0);
            $table->string('ref_type', 32);
            $table->unsignedBigInteger('ref_id');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['ref_type', 'ref_id']);
            $table->index(['product_id', 'created_at']);
            $table->index(['batch_id', 'created_at']);
        });

        // Partition by year ditunda ke T20 (lihat T07 — MySQL FK tidak kompatibel
        // dengan native partitioning). Indeks pada `created_at` sudah cukup
        // untuk MVP < 1jt baris/tahun.
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger');
    }
};

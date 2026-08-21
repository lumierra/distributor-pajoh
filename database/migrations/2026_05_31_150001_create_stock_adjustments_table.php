<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyesuaian stok manual (adjustment) — koreksi stok sistem terhadap kondisi
 * fisik di luar alur GRN/penjualan: barang rusak, hilang, susut, salah hitung,
 * temuan lebih. Header + item (multi-produk/batch). Alur draft → posted; saat
 * posting, tiap item menulis stock_ledger bertipe adjustment_in/out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->string('adjustment_number', 32)->unique();
            $table->date('adjustment_date')->index();
            // Kategori alasan (rusak/hilang/susut/koreksi/temuan_lebih/lainnya).
            $table->string('reason_category', 32);
            $table->text('notes')->nullable();
            $table->string('status', 16)->default('draft')->index(); // draft | posted | cancelled
            $table->unsignedSmallInteger('fiscal_year');

            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('product_batches')->restrictOnDelete();
            // Snapshot identitas (aman kalau produk/batch berubah nama nanti).
            $table->string('product_name_snapshot', 191);
            $table->string('product_sku_snapshot', 64);
            $table->string('batch_code_snapshot', 64);
            // Arah koreksi + qty (base unit).
            $table->string('direction', 4); // in | out
            $table->unsignedInteger('qty'); // > 0
            $table->unsignedInteger('system_qty_snapshot')->default(0); // stok sistem saat baris dibuat
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('stock_adjustment_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
    }
};

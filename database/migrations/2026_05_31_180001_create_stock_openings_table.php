<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok Awal (opening balance) — memasukkan stok yang SUDAH ada di dunia nyata
 * saat aplikasi mulai dipakai, tanpa melalui PO/GRN. Header + item (multi-produk).
 * Beda dengan Adjustment: item membuat batch baru (produk baru belum punya batch),
 * bukan memilih batch yang sudah ada. Alur draft → posted; saat posting tiap item
 * menulis stock_ledger bertipe opening_in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_openings', function (Blueprint $table): void {
            $table->id();
            $table->string('opening_number', 32)->unique();
            $table->date('opening_date')->index();
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

        Schema::create('stock_opening_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opening_id')->constrained('stock_openings')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            // Supplier opsional — boleh diisi kalau ingin batch tertaut supplier.
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            // Batch dibuat saat posting; batch_id diisi setelahnya.
            $table->foreignId('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();
            // Input batch (opsional; kalau kosong dipakai default "OPENING").
            $table->string('batch_code', 64);
            $table->date('expired_date')->nullable();
            // Snapshot identitas produk.
            $table->string('product_name_snapshot', 191);
            $table->string('product_sku_snapshot', 64);
            // Qty (base unit) + harga modal per base unit.
            $table->unsignedInteger('qty'); // > 0
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('stock_opening_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opening_items');
        Schema::dropIfExists('stock_openings');
    }
};

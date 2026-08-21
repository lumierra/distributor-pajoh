<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock opname — sesi perhitungan fisik gudang. Header + item per batch. Item
 * di-auto-generate dari semua batch berstok saat sesi dibuat (snapshot
 * system_qty), petugas isi counted_qty (hasil hitung fisik). Saat posting,
 * variance (fisik − sistem terkini) menulis stock_ledger opname_in/out.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table): void {
            $table->id();
            $table->string('opname_number', 32)->unique();
            $table->date('opname_date')->index();
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

        Schema::create('stock_opname_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->string('product_name_snapshot', 191);
            $table->string('product_sku_snapshot', 64);
            $table->string('batch_code_snapshot', 64);
            // Snapshot stok sistem saat sesi dibuat (referensi).
            $table->integer('system_qty_snapshot')->default(0);
            // Hasil hitung fisik (null = belum dihitung).
            $table->unsignedInteger('counted_qty')->nullable();
            // Variance saat posting (fisik − sistem terkini). Diisi saat posting.
            $table->integer('variance')->default(0);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('stock_opname_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};

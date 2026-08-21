<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GRN kini mendukung "penerimaan langsung" tanpa Purchase Order: petugas
 * gudang input supplier + barang sendiri lalu terima sekaligus. Karena itu
 * kolom penghubung ke PO harus boleh kosong:
 *  - goods_receipts.purchase_order_id
 *  - grn_items.po_item_id
 *
 * supplier_id di goods_receipts SENGAJA tetap wajib — jadi sumber batch &
 * snapshot supplier untuk kedua mode (dengan / tanpa PO).
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL: drop FK dulu sebelum mengubah kolom, lalu pasang ulang sbg nullOnDelete.
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropForeign(['purchase_order_id']);
        });
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
        });

        Schema::table('grn_items', function (Blueprint $table): void {
            $table->dropForeign(['po_item_id']);
        });
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->foreignId('po_item_id')->nullable()->change();
            $table->foreign('po_item_id')->references('id')->on('po_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->dropForeign(['po_item_id']);
        });
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->foreignId('po_item_id')->nullable(false)->change();
            $table->foreign('po_item_id')->references('id')->on('po_items')->restrictOnDelete();
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropForeign(['purchase_order_id']);
        });
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->foreignId('purchase_order_id')->nullable(false)->change();
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->restrictOnDelete();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * qty_delivery_note = jumlah barang menurut SURAT JALAN supplier. Dipakai untuk
 * menghitung "pending" pada penerimaan langsung (tanpa PO): pending = surat
 * jalan − qty diterima. Stok tetap dari qty_reguler (barang fisik nyata) —
 * kolom ini murni catatan/acuan, tidak menyentuh perhitungan stok.
 *
 * Untuk GRN dari PO, kolom ini diabaikan (pending sudah dihitung dari PO).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->unsignedInteger('qty_delivery_note')->default(0)->after('qty_reguler');
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->dropColumn('qty_delivery_note');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "pending penerimaan langsung sudah selesai/lunas". Pending pada GRN
 * tanpa PO (qty_delivery_note − qty_reguler) hanya catatan; tidak ada alur
 * susulan otomatis seperti PO. Saat barang susulan akhirnya datang, admin
 * menandai GRN ini selesai supaya pending-nya berhenti dihitung di halaman
 * stok. Angka historis GRN tidak diubah — hanya ditandai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->timestamp('direct_pending_settled_at')->nullable()->after('discrepancy_notes');
            $table->foreignId('direct_pending_settled_by')->nullable()->after('direct_pending_settled_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('direct_pending_settled_by');
            $table->dropColumn('direct_pending_settled_at');
        });
    }
};

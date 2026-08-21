<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pindahkan penanda "pending penerimaan langsung selesai" dari level GRN
 * (goods_receipts) ke level ITEM (grn_items). Alasan: pending menyusul di
 * lapangan datang per-produk, tidak selalu bareng — jadi tiap baris item
 * ditandai selesai sendiri. Kolom lama di goods_receipts di-drop (belum
 * dipakai di produksi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->timestamp('pending_settled_at')->nullable()->after('qty_delivery_note');
            $table->foreignId('pending_settled_by')->nullable()->after('pending_settled_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('direct_pending_settled_by');
            $table->dropColumn('direct_pending_settled_at');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table): void {
            $table->timestamp('direct_pending_settled_at')->nullable()->after('discrepancy_notes');
            $table->foreignId('direct_pending_settled_by')->nullable()->after('direct_pending_settled_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('grn_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pending_settled_by');
            $table->dropColumn('pending_settled_at');
        });
    }
};

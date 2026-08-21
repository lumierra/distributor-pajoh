<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diskon per-item (tipe %/Rp) untuk SO & cashback tingkat SO.
 *
 * - so_items: `discount_type` (percent|rp) + `discount_value` menggantikan pola
 *   Z1/Z2 di UI. Kolom z1/z2 lama DIBIARKAN (default 0) demi kompatibilitas
 *   invoice lama; unit_net_price tetap jadi "jangkar" yang dipakai downstream.
 * - sales_orders: `cashback` = potongan rupiah tambahan tingkat SO (di luar
 *   header discount), mengurangi total.
 * - invoices: `cashback_amount` = porsi cashback yang dialokasikan ke faktur ini
 *   (prorata seperti header_discount_amount).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('so_items', function (Blueprint $table): void {
            $table->string('discount_type', 8)->nullable()->after('discount_z2_pct'); // percent | rp
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->string('discount_type', 8)->nullable()->after('discount_z2_pct');
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
        });

        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->decimal('cashback', 15, 2)->default(0)->after('header_discount_amount');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->decimal('cashback_amount', 15, 2)->default(0)->after('header_discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('so_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
        Schema::table('sales_orders', function (Blueprint $table): void {
            $table->dropColumn('cashback');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('cashback_amount');
        });
    }
};

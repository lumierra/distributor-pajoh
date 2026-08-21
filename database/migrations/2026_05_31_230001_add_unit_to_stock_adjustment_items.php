<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adjustment: pilih SATUAN per item (seperti PO/GRN/Stok Awal). `qty` disimpan
 * dalam UoM yang dipilih; `qty_base` = hasil konversi (× qty_to_base) yang
 * benar-benar disesuaikan ke stok saat posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table): void {
            $table->foreignId('product_unit_id')->nullable()->after('product_id')
                ->constrained('product_units')->nullOnDelete();
            $table->string('product_unit_name_snapshot', 32)->nullable()->after('product_unit_id');
            $table->unsignedInteger('qty_base')->default(0)->after('qty'); // qty × qty_to_base
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustment_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_unit_id');
            $table->dropColumn(['product_unit_name_snapshot', 'qty_base']);
        });
    }
};

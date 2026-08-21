<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stok Awal: qty BONUS per item (barang gratis) — masuk ke bonus_pool terpisah,
 * seperti bonus di GRN. `qty_bonus` dalam UoM dipilih; `qty_bonus_base` = konversi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_opening_items', function (Blueprint $table): void {
            $table->unsignedInteger('qty_bonus')->default(0)->after('qty_base');
            $table->unsignedInteger('qty_bonus_base')->default(0)->after('qty_bonus');
        });
    }

    public function down(): void
    {
        Schema::table('stock_opening_items', function (Blueprint $table): void {
            $table->dropColumn(['qty_bonus', 'qty_bonus_base']);
        });
    }
};

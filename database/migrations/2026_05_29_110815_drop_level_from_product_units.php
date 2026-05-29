<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop kolom level + unique constraint (product_id, level) di product_units.
 * Konsep level KCL/TGH/BSR sudah dihapus — satuan jadi dinamis tanpa hierarchy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table): void {
            // FK product_id butuh index. Sebelum drop unique compound,
            // bikin index baru di product_id saja supaya FK tetap valid.
            $table->index('product_id', 'product_units_product_id_index');
        });

        Schema::table('product_units', function (Blueprint $table): void {
            $table->dropUnique(['product_id', 'level']);
            $table->dropIndex(['level']);
            $table->dropColumn('level');
        });
    }

    public function down(): void
    {
        // No restore — level dihapus permanen.
    }
};

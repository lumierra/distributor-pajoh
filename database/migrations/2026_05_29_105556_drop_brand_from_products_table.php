<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop kolom products.brand — diganti dgn tagging supplier
 * (supplier_products M2M).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('products', 'brand')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('brand');
            });
        }
    }

    public function down(): void
    {
        // No restore — brand dihapus permanen.
    }
};

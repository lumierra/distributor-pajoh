<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop semua skema pricing lama yang berbasis price_tier.
 *
 * Pricing baru = (product × unit × supplier) via supplier_product_units.
 * Dev environment — data lama tidak perlu dipreserve (user konfirmasi).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('product_price_history');
        Schema::dropIfExists('product_prices');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropForeign(['price_tier_id']);
            $table->dropColumn('price_tier_id');
        });

        Schema::dropIfExists('price_tiers');

        if (Schema::hasColumn('supplier_products', 'default_cost_price')) {
            Schema::table('supplier_products', function (Blueprint $table): void {
                $table->dropColumn('default_cost_price');
            });
        }
    }

    public function down(): void
    {
        // No restore — kalau perlu rollback, harus mig:fresh ulang DB
        // dan re-seed dari sumber master.
    }
};

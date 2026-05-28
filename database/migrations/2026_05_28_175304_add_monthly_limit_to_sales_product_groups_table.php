<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_product_groups', function (Blueprint $table): void {
            $table->decimal('monthly_limit', 15, 2)->nullable()->after('product_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_product_groups', function (Blueprint $table): void {
            $table->dropColumn('monthly_limit');
        });
    }
};

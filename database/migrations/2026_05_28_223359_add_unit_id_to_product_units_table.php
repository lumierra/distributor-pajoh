<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_units', function (Blueprint $table): void {
            $table->foreignId('unit_id')
                ->nullable()
                ->after('product_id')
                ->constrained('units')
                ->nullOnDelete();
            $table->index('unit_id', 'product_units_unit_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('product_units', function (Blueprint $table): void {
            $table->dropForeign(['unit_id']);
            $table->dropIndex('product_units_unit_id_index');
            $table->dropColumn('unit_id');
        });
    }
};

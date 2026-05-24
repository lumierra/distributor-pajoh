<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->unsignedInteger('qty_returned_to_supplier')->default(0)->after('qty_damaged');
        });
    }

    public function down(): void
    {
        Schema::table('grn_items', function (Blueprint $table): void {
            $table->dropColumn('qty_returned_to_supplier');
        });
    }
};

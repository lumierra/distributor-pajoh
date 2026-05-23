<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_group_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_group_id')->constrained('product_groups')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['product_group_id', 'product_id'], 'pgi_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_group_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->enum('level', ['BSR', 'TGH', 'KCL']);
            $table->string('name', 32);
            $table->unsignedInteger('qty_to_base'); // konversi ke base unit (KCL)
            $table->string('barcode', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['product_id', 'level']);
            $table->unique('barcode');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};

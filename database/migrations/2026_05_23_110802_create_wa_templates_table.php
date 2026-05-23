<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 64);
            $table->string('name', 128);
            $table->text('body');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('available_placeholders')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('category');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group', 64);
            $table->string('key', 128);
            $table->json('value')->nullable();
            $table->json('default_value')->nullable();
            $table->string('type', 16);
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->string('validation', 255)->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['group', 'key']);
            $table->index('group');
            $table->index('is_sensitive');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

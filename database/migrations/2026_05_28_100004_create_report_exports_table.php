<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('report_type', 64);
            $table->string('format', 16);
            $table->json('filters')->nullable();
            $table->integer('rows_count')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->timestamp('exported_at')->useCurrent();

            $table->index('report_type');
            $table->index('exported_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};

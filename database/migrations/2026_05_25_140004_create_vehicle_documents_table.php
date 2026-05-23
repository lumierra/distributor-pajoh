<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            $table->string('type', 32)->index();
            $table->string('title', 128);
            $table->string('file_path', 255);
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_mime', 64)->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expires_date')->nullable()->index();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_documents');
    }
};

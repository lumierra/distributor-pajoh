<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('type', 32); // NPWP/NIB/PKP/KONTRAK/SURAT_PERJANJIAN/LAINNYA
            $table->string('title', 128);
            $table->string('file_path', 255);
            $table->unsignedInteger('file_size')->nullable();
            $table->string('file_mime', 64)->nullable();
            $table->date('issued_date')->nullable();
            $table->date('expires_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('expires_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_documents');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lampiran surat penerimaan untuk GRN — multi-file (foto/PDF/dll), berlaku
 * baik GRN dari PO maupun penerimaan langsung. One-to-many ke goods_receipts.
 * Mengikuti konvensi supplier_documents (file_path/file_mime/file_size).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->string('file_path', 255);
            $table->string('original_name', 255);
            $table->string('file_mime', 128)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('goods_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_attachments');
    }
};

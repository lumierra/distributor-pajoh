<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('po_number', 32);
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->json('supplier_snapshot')->nullable();

            // Tanggal
            $table->date('po_date')->index();
            $table->date('eta_date')->nullable();
            $table->unsignedSmallInteger('payment_term_days')->nullable();

            // Status
            $table->string('status', 24)->default('draft')->index();
            $table->smallInteger('fiscal_year')->index();
            $table->boolean('is_carry_over')->default(false)->index();

            // Total
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('header_discount_type', 8)->nullable();
            $table->decimal('header_discount_value', 15, 2)->default(0);
            $table->decimal('header_discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->text('notes')->nullable();

            // Workflow
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('close_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();

            // PDF
            $table->string('pdf_path', 255)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('po_number');
        });

        // NB: partition by fiscal_year ditunda — MySQL native partitioning
        // tidak mendukung FK lintas-tabel (po_items → purchase_orders).
        // Pakai indeks pada `fiscal_year` saja untuk MVP; partition dipasang
        // saat T20 (year-end closing) bersamaan dengan strategi archival.
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};

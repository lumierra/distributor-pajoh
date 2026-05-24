<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_orders', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('so_number', 32);
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->json('customer_snapshot')->nullable();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            // visit_id ditautkan ke sales_visits (T10). Nullable, no FK dulu.
            $table->unsignedBigInteger('visit_id')->nullable();

            // Tanggal
            $table->date('so_date')->index();
            $table->date('eta_date')->nullable();
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->date('due_date')->nullable()->index();

            // Status
            $table->string('status', 32)->default('draft')->index();
            $table->smallInteger('fiscal_year')->index();
            $table->boolean('is_carry_over')->default(false)->index();

            // Pricing
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('header_discount_type', 8)->nullable();
            $table->decimal('header_discount_value', 15, 2)->default(0);
            $table->decimal('header_discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);

            $table->text('notes')->nullable();

            // Workflow
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            // Credit review
            $table->boolean('credit_review_required')->default(false);
            $table->decimal('credit_outstanding_snapshot', 15, 2)->nullable();
            $table->foreignId('credit_override_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('credit_override_approved_at')->nullable();
            $table->text('credit_override_reason')->nullable();

            // PDF (opsional)
            $table->string('pdf_path', 255)->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('so_number');
            $table->index('sales_id');
            $table->index('visit_id');
        });

        // Partition ditunda ke T20 (FK ke so_items + sales_orders.so_item_id
        // dari so_reservations tidak kompatibel dengan MySQL partitioning).
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};

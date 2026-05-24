<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('invoice_number', 48)->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->foreignId('delivery_order_id')->constrained('delivery_orders')->restrictOnDelete();

            // Customer
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->json('customer_snapshot')->nullable();

            // Sales
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sales_name_snapshot', 128)->nullable();

            // Driver/Vehicle snapshot
            $table->string('driver_name_snapshot', 128)->nullable();
            $table->string('vehicle_plate_snapshot', 32)->nullable();

            // Tanggal
            $table->date('invoice_date')->index();
            $table->unsignedSmallInteger('payment_term_days')->default(0);
            $table->date('due_date')->index();
            $table->date('original_due_date')->nullable();

            // Jenis bayar
            $table->boolean('is_cash')->default(false);

            // Status
            $table->string('status', 16)->default('open')->index();
            $table->smallInteger('fiscal_year')->index();
            $table->boolean('is_carry_over')->default(false)->index();

            // Amounts
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('header_discount_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('outstanding', 15, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('delivery_notes_snapshot')->nullable();

            // PDF
            $table->string('pdf_path', 255)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            // Tracking
            $table->timestamp('last_reminder_sent_at')->nullable();
            $table->timestamp('overdue_set_at')->nullable();
            $table->timestamp('paid_at')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // 1 DO = 1 Invoice (idempotency hard guarantee)
            $table->unique('delivery_order_id', 'invoices_do_unique');
            $table->index('sales_order_id');
            $table->index('customer_id');
            $table->index('sales_id');
        });

        // Partition ditunda ke T20.
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

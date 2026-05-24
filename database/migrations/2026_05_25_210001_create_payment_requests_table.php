<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_requests', function (Blueprint $table): void {
            $table->id();

            // Sumber
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->json('customer_snapshot')->nullable();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();

            // Detail
            $table->decimal('amount', 15, 2);
            $table->string('method', 16);
            $table->string('reference_no', 64)->nullable();
            $table->string('bank_name', 64)->nullable();
            $table->date('giro_due_date')->nullable();
            $table->timestamp('paid_at');

            // Bukti
            $table->string('proof_image_path', 255)->nullable();
            $table->text('notes')->nullable();

            // Status
            $table->string('status', 16)->default('draft')->index();

            // Workflow timestamps
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_id');
            $table->index('customer_id');
            $table->index('sales_id');
            $table->index('method');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_requests');
    }
};

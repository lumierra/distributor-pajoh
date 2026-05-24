<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_number', 32)->unique();
            $table->foreignId('payment_request_id')->constrained('payment_requests')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->json('invoice_snapshot')->nullable();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // Detail
            $table->decimal('amount', 15, 2);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('overpayment_amount', 15, 2)->default(0);
            $table->string('method', 16);
            $table->string('reference_no', 64)->nullable();
            $table->string('bank_name', 64)->nullable();
            $table->date('giro_due_date')->nullable()->index();

            // Tanggal
            $table->timestamp('paid_at')->index();
            $table->timestamp('verified_at');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamp('applied_to_invoice_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->text('bounce_reason')->nullable();

            // Status
            $table->string('status', 16)->default('posted')->index();
            $table->smallInteger('fiscal_year')->index();

            // Bukti
            $table->string('proof_image_path', 255)->nullable();
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('cleared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('bounced_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('invoice_id');
            $table->index('customer_id');
            $table->index('payment_request_id');
            $table->index('method');
        });

        // Partition ditunda T20.
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

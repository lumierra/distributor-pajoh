<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 32)->unique();

            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->json('customer_snapshot')->nullable();

            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('visit_id')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained('delivery_orders')->nullOnDelete();

            $table->date('return_date');
            $table->string('brand_tag', 64)->nullable()->index();
            $table->string('reason_code', 32)->nullable();
            $table->text('reason_notes')->nullable();

            $table->string('status', 16)->default('draft')->index();
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->boolean('is_carry_over')->default(false);

            $table->decimal('total_value', 15, 2)->default(0);
            $table->unsignedBigInteger('total_qty_good_base')->default(0);
            $table->unsignedBigInteger('total_qty_bs_base')->default(0);

            $table->foreignId('credit_note_id')->nullable();

            $table->timestamp('sorted_at')->nullable();
            $table->foreignId('sorted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            $table->string('proof_photo_path', 255)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('return_date');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_returns');
    }
};

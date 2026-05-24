<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('year_end_closings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year')->unique();
            $table->date('closing_date');
            $table->string('status', 16)->default('in_progress')->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();

            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('total_margin', 15, 2)->default(0);
            $table->decimal('margin_percent', 7, 2)->default(0);
            $table->decimal('total_purchases', 15, 2)->default(0);
            $table->decimal('total_payments_received', 15, 2)->default(0);
            $table->decimal('total_outstanding_carry_over', 15, 2)->default(0);
            $table->decimal('total_stock_value_closing', 15, 2)->default(0);

            $table->unsignedInteger('invoice_count')->default(0);
            $table->unsignedInteger('customer_count_active')->default(0);
            $table->unsignedInteger('product_count_sold')->default(0);

            $table->json('top_5_customers')->nullable();
            $table->json('top_5_products')->nullable();
            $table->json('top_3_sales')->nullable();
            $table->json('carry_over_summary')->nullable();
            $table->json('carry_over_po_ids')->nullable();
            $table->json('carry_over_so_ids')->nullable();
            $table->json('carry_over_invoice_ids')->nullable();
            $table->json('carry_over_cn_ids')->nullable();

            $table->string('summary_pdf_path', 255)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('pre_check_passed_at')->nullable();
            $table->timestamp('carry_over_done_at')->nullable();
            $table->timestamp('sequence_reset_done_at')->nullable();
            $table->timestamp('partition_setup_done_at')->nullable();
            $table->timestamp('summary_generated_at')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('year_end_closings');
    }
};

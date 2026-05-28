<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_supplier_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['customer_id', 'supplier_id'], 'psa_customer_supplier_idx');
            $table->index(['invoice_id', 'supplier_id'], 'psa_invoice_supplier_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_supplier_allocations');
    }
};

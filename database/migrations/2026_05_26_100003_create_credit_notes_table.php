<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();
            $table->string('cn_number', 32)->unique();
            $table->foreignId('customer_return_id')->constrained('customer_returns')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->date('cn_date');
            $table->decimal('amount', 15, 2);
            $table->decimal('applied_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->string('status', 16)->default('open')->index();
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('cn_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};

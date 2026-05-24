<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 32)->unique();

            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->json('supplier_snapshot')->nullable();

            $table->date('return_date');
            $table->date('sent_date')->nullable();
            $table->date('settled_date')->nullable();

            $table->string('reason_code', 32)->nullable();
            $table->text('reason_notes')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->unsignedSmallInteger('fiscal_year')->index();
            $table->boolean('is_carry_over')->default(false);

            $table->decimal('claim_amount', 15, 2)->default(0);
            $table->decimal('settled_amount', 15, 2)->nullable();
            $table->string('settlement_type', 16)->nullable();
            $table->text('settlement_notes')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            $table->text('notes')->nullable();
            $table->string('proof_photo_path', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('return_date');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_returns');
    }
};

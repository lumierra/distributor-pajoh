<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_extension_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();

            // Request
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at')->useCurrent();
            $table->date('old_due_date');
            $table->date('new_due_date_requested');
            $table->text('request_reason');

            // Approval
            $table->string('status', 16)->default('pending')->index();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('approved_new_due_date')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_extension_logs');
    }
};

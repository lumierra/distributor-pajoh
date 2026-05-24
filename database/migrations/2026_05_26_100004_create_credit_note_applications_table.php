<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_note_applications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('credit_note_id')->constrained('credit_notes')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->timestamp('applied_at')->useCurrent();
            $table->foreignId('applied_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('credit_note_id');
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_note_applications');
    }
};

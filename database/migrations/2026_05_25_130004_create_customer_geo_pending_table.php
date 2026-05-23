<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_geo_pending', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // visit_id FK ke sales_visits dibuat di Topik 10
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->decimal('captured_latitude', 10, 7);
            $table->decimal('captured_longitude', 10, 7);
            $table->timestamp('captured_at');
            $table->foreignId('captured_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('accuracy_meter')->nullable();
            $table->string('status', 16)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_geo_pending');
    }
};

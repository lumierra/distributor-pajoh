<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_visit_bypass_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->text('reason');
            $table->decimal('requested_lat', 10, 7)->nullable();
            $table->decimal('requested_lng', 10, 7)->nullable();
            $table->unsignedInteger('distance_meter')->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->string('status', 16)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->text('notes')->nullable();

            $table->index('sales_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_visit_bypass_requests');
    }
};

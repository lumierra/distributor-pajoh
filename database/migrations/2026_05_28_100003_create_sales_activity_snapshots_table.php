<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_activity_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->foreignId('sales_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedInteger('total_visits')->default(0);
            $table->unsignedInteger('valid_visits')->default(0);
            $table->unsignedInteger('unique_customers_visited')->default(0);
            $table->unsignedInteger('total_visit_duration_min')->default(0);

            $table->unsignedInteger('so_count')->default(0);
            $table->decimal('so_value', 15, 2)->default(0);
            $table->unsignedInteger('so_approved_count')->default(0);
            $table->unsignedInteger('so_cancelled_count')->default(0);

            $table->decimal('conversion_rate', 7, 2)->default(0);
            $table->decimal('target_value', 15, 2)->nullable();
            $table->decimal('achievement_percent', 7, 2)->nullable();

            $table->timestamp('created_at')->nullable();

            $table->unique(['snapshot_date', 'sales_id'], 'sas_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_activity_snapshots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ar_aging_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->date('snapshot_date')->index();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->decimal('bucket_0_30', 15, 2)->default(0);
            $table->decimal('bucket_31_60', 15, 2)->default(0);
            $table->decimal('bucket_61_90', 15, 2)->default(0);
            $table->decimal('bucket_over_90', 15, 2)->default(0);
            $table->decimal('total_outstanding', 15, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['snapshot_date', 'customer_id'], 'ar_aging_unique');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ar_aging_snapshots');
    }
};

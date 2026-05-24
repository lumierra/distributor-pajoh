<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('sales_schedules')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('user_devices')->nullOnDelete();

            // Check-in
            $table->timestamp('checked_in_at');
            $table->decimal('checkin_latitude', 10, 7);
            $table->decimal('checkin_longitude', 10, 7);
            $table->unsignedInteger('checkin_accuracy_meter')->nullable();
            $table->unsignedInteger('checkin_distance_to_outlet')->nullable();
            $table->string('checkin_photo_path', 255)->nullable();
            $table->text('checkin_notes')->nullable();
            $table->boolean('is_mock_location')->default(false);
            $table->boolean('bypass_geofence')->default(false);
            $table->text('bypass_reason')->nullable();
            $table->foreignId('bypass_granted_by')->nullable()->constrained('users')->nullOnDelete();

            // Check-out
            $table->timestamp('checked_out_at')->nullable();
            $table->decimal('checkout_latitude', 10, 7)->nullable();
            $table->decimal('checkout_longitude', 10, 7)->nullable();
            $table->text('checkout_notes')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->boolean('auto_checked_out')->default(false);
            $table->string('auto_checkout_reason', 64)->nullable();

            // Status & aktivitas
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('so_count')->default(0);
            $table->decimal('so_total_value', 15, 2)->default(0);
            $table->unsignedInteger('return_count')->default(0);
            $table->unsignedInteger('photo_count')->default(0);

            // Audit
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index('sales_id');
            $table->index('customer_id');
            $table->index('schedule_id');
            $table->index('status');
            $table->index('checked_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_visits');
    }
};

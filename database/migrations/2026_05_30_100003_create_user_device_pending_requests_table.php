<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_device_pending_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_uuid', 128);
            $table->string('mac_address', 64)->nullable();
            $table->string('device_name', 128)->nullable();
            $table->string('device_model', 64)->nullable();
            $table->string('os', 32)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('requested_ip', 45)->nullable();
            $table->timestamp('requested_at')->useCurrent();
            $table->string('status', 16)->default('pending');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('activated_device_id')->nullable()->constrained('user_devices')->nullOnDelete();
            $table->text('notes')->nullable();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_device_pending_requests');
    }
};

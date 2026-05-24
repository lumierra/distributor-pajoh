<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_uuid', 128);
            $table->string('mac_address', 64)->nullable();
            $table->string('device_name', 128)->nullable();
            $table->string('device_model', 64)->nullable();
            $table->string('os', 32)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamp('registered_at');
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'device_uuid'], 'user_devices_user_uuid_unique');
            $table->index('status');
            $table->index('device_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};

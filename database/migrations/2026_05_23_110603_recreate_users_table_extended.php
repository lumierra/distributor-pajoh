<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('name', 128);
            $table->string('username', 64);
            $table->string('email', 128)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('password');
            $table->rememberToken();

            // Role & status
            $table->foreignId('role_id')->constrained('roles')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('force_password_change')->default(true);
            $table->timestamp('password_changed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            // Address & emergency
            $table->text('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('nik', 32)->nullable();
            $table->string('emergency_contact_name', 128)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();
            $table->date('hire_date')->nullable();

            // Sales-specific
            $table->string('photo_ktp_path', 255)->nullable();
            $table->string('default_area', 128)->nullable();
            $table->decimal('monthly_target', 15, 2)->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('username');
            $table->index('email');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

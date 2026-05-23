<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('code', 32)->unique();
            $table->string('name', 128)->index();
            $table->string('nik', 32)->nullable();
            $table->string('phone', 32)->nullable()->index();
            $table->string('whatsapp', 32)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 64)->nullable();

            // License
            $table->string('license_no', 32)->nullable();
            $table->string('license_type', 16)->nullable();
            $table->date('license_expired_date')->nullable()->index();

            // Emergency
            $table->string('emergency_contact_name', 128)->nullable();
            $table->string('emergency_contact_phone', 32)->nullable();

            // Operasional
            $table->date('hire_date')->nullable();
            $table->foreignId('default_vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            // Status
            $table->boolean('is_active')->default(true)->index();
            $table->string('status', 16)->default('idle')->index();
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('plate_number', 16)->unique();
            $table->string('type', 16)->index();
            $table->string('brand', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->smallInteger('year')->nullable();
            $table->string('color', 32)->nullable();
            $table->decimal('capacity_kg', 10, 2)->nullable();
            $table->decimal('capacity_kubik', 10, 3)->nullable();
            $table->date('last_service_date')->nullable();
            $table->date('next_service_date')->nullable()->index();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('status', 16)->default('idle')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};

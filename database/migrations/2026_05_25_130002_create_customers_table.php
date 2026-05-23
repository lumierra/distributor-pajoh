<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('code', 32)->unique();
            $table->string('name', 128)->index();
            $table->string('owner_name', 128)->nullable();
            $table->foreignId('customer_type_id')->nullable()->constrained('customer_types')->nullOnDelete();
            $table->foreignId('price_tier_id')->constrained('price_tiers')->restrictOnDelete();
            $table->string('npwp', 32)->nullable();

            // Kontak
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('email', 128)->nullable();

            // Alamat
            $table->text('address')->nullable();
            $table->string('city', 64)->nullable()->index();
            $table->string('province', 64)->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('area', 128)->nullable()->index();

            // GPS
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('geo_confirmed_at')->nullable();
            $table->foreignId('geo_confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('geo_captured_by')->nullable()->constrained('users')->nullOnDelete();

            // Sales & finance
            $table->foreignId('assigned_sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->unsignedSmallInteger('payment_term_days')->default(0);

            // Status & notes
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};

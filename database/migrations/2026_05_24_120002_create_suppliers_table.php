<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('code', 32);
            $table->string('name', 128);
            $table->string('legal_form', 16)->nullable(); // PT/CV/UD/KOPERASI/PABRIK/LAINNYA
            $table->string('npwp', 32)->nullable();
            $table->string('nib', 32)->nullable();
            $table->foreignId('supplier_category_id')->nullable()
                ->constrained('supplier_categories')->nullOnDelete();

            // Kontak
            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('email', 128)->nullable();
            $table->string('fax', 32)->nullable();

            // Alamat
            $table->text('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('province', 64)->nullable();
            $table->string('postal_code', 16)->nullable();

            // PIC
            $table->string('contact_person_name', 128)->nullable();
            $table->string('contact_person_role', 64)->nullable();
            $table->string('contact_person_phone', 32)->nullable();
            $table->string('contact_person_email', 128)->nullable();

            // Operasional
            $table->unsignedSmallInteger('payment_term_days')->nullable();
            $table->unsignedSmallInteger('default_lead_time_days')->nullable();

            // Status & misc
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code');
            $table->index('name');
            $table->index('npwp');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};

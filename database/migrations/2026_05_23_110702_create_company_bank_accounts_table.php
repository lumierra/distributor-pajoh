<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('bank_name', 64);
            $table->string('bank_code', 16)->nullable();
            $table->string('account_number', 32);
            $table->string('account_holder', 128);
            $table->string('branch', 128)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_on_invoice')->default(true);
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};

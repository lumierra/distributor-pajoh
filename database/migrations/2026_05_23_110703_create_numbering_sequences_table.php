<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('doc_type', 32);
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month')->nullable();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['doc_type', 'period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_sequences');
    }
};

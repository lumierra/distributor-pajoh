<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('password_reset_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('reset_by_user_id')->constrained('users');
            $table->timestamp('reset_at')->useCurrent();
            $table->string('ip', 45);
            $table->string('user_agent', 512)->nullable();
            $table->text('reason')->nullable();

            $table->index('user_id');
            $table->index('reset_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_logs');
    }
};

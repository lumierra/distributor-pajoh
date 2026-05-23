<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name_snapshot', 128)->nullable();
            $table->string('action', 64);
            $table->string('model_type', 128)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('model_label', 255)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('context')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('channel', 16)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->index('user_id');
            $table->index(['model_type', 'model_id']);
            $table->index('action');
            $table->index('created_at');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE activity_logs DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');
            DB::statement("ALTER TABLE activity_logs PARTITION BY RANGE COLUMNS(created_at) (
                PARTITION p2026 VALUES LESS THAN ('2027-01-01 00:00:00'),
                PARTITION p2027 VALUES LESS THAN ('2028-01-01 00:00:00'),
                PARTITION p2028 VALUES LESS THAN ('2029-01-01 00:00:00'),
                PARTITION p_future VALUES LESS THAN (MAXVALUE)
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};

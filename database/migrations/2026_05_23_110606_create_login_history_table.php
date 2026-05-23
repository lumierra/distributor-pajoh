<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('username_input', 64);
            $table->boolean('is_successful');
            $table->string('failure_reason', 64)->nullable();
            $table->string('ip', 45);
            $table->string('user_agent', 512)->nullable();
            $table->string('device_uuid', 128)->nullable();
            $table->string('channel', 16);
            $table->dateTime('login_at')->useCurrent();

            $table->index('user_id');
            $table->index('login_at');
            $table->index('ip');
            $table->index('is_successful');
        });

        // MySQL partitioning requires every unique key to include the partition column,
        // so we redefine the PK as (id, login_at) and apply RANGE partitioning by YEAR.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE login_history DROP PRIMARY KEY, ADD PRIMARY KEY (id, login_at)');
            DB::statement("ALTER TABLE login_history PARTITION BY RANGE COLUMNS(login_at) (
                PARTITION p2026 VALUES LESS THAN ('2027-01-01 00:00:00'),
                PARTITION p2027 VALUES LESS THAN ('2028-01-01 00:00:00'),
                PARTITION p2028 VALUES LESS THAN ('2029-01-01 00:00:00'),
                PARTITION p_future VALUES LESS THAN (MAXVALUE)
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('login_history');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_history', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_unit_id');
            $table->unsignedBigInteger('price_tier_id');
            $table->decimal('old_price', 15, 2)->nullable();
            $table->decimal('new_price', 15, 2);
            $table->string('change_reason', 255)->nullable();
            $table->unsignedBigInteger('bulk_update_id')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->dateTime('changed_at')->useCurrent();

            $table->index(['product_id', 'changed_at']);
            $table->index('changed_at');
        });

        // Partition by year — sama pattern dengan activity_logs & login_history
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE product_price_history DROP PRIMARY KEY, ADD PRIMARY KEY (id, changed_at)');
            DB::statement("ALTER TABLE product_price_history PARTITION BY RANGE COLUMNS(changed_at) (
                PARTITION p2026 VALUES LESS THAN ('2027-01-01 00:00:00'),
                PARTITION p2027 VALUES LESS THAN ('2028-01-01 00:00:00'),
                PARTITION p2028 VALUES LESS THAN ('2029-01-01 00:00:00'),
                PARTITION p_future VALUES LESS THAN (MAXVALUE)
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_history');
    }
};

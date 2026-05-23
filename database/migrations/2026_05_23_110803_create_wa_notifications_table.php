<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_notifications', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('category', 64);
            $table->string('recipient_type', 16);
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_name', 128)->nullable();
            $table->string('recipient_phone', 32);
            $table->string('template_used', 64)->nullable();
            $table->text('message');
            $table->json('context_data')->nullable();
            $table->string('related_ref_type', 64)->nullable();
            $table->unsignedBigInteger('related_ref_id')->nullable();

            $table->string('status', 16)->default('pending');
            $table->string('skip_reason', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('gateway_message_id', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);

            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->boolean('is_manual')->default(false);

            $table->dateTime('created_at')->useCurrent();
            $table->timestamp('updated_at')->nullable();

            $table->index('category');
            $table->index(['recipient_type', 'recipient_id']);
            $table->index('status');
            $table->index('sent_at');
            $table->index(['related_ref_type', 'related_ref_id']);
            $table->index('recipient_phone');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE wa_notifications DROP PRIMARY KEY, ADD PRIMARY KEY (id, created_at)');
            DB::statement("ALTER TABLE wa_notifications PARTITION BY RANGE COLUMNS(created_at) (
                PARTITION p2026 VALUES LESS THAN ('2027-01-01 00:00:00'),
                PARTITION p2027 VALUES LESS THAN ('2028-01-01 00:00:00'),
                PARTITION p2028 VALUES LESS THAN ('2029-01-01 00:00:00'),
                PARTITION p_future VALUES LESS THAN (MAXVALUE)
            )");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_notifications');
    }
};

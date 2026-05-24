<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_orders', function (Blueprint $table): void {
            $table->id();

            // Identitas
            $table->string('do_number', 32)->unique();
            $table->foreignId('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->json('delivery_address_snapshot')->nullable();

            // Tanggal
            $table->date('do_date')->index();
            $table->date('expected_delivery_date')->nullable();

            // Status
            $table->string('status', 24)->default('draft')->index();
            $table->smallInteger('fiscal_year')->index();
            $table->boolean('is_carry_over')->default(false);

            // Driver & Vehicle (assigned saat picking/packed)
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->json('driver_snapshot')->nullable();
            $table->json('vehicle_snapshot')->nullable();

            // Workflow timestamps
            $table->timestamp('picking_started_at')->nullable();
            $table->foreignId('picking_started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('packed_at')->nullable();
            $table->foreignId('packed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('in_transit_at')->nullable();
            $table->foreignId('in_transit_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();

            // Delivery confirmation
            $table->string('receiver_name', 128)->nullable();
            $table->text('receiver_notes')->nullable();
            $table->string('proof_photo_signed_path', 255)->nullable();
            $table->string('proof_photo_goods_path', 255)->nullable();
            $table->string('digital_signature_path', 255)->nullable();
            $table->decimal('delivery_latitude', 10, 7)->nullable();
            $table->decimal('delivery_longitude', 10, 7)->nullable();

            // Discrepancy
            $table->boolean('has_partial_return')->default(false);
            $table->text('partial_return_notes')->nullable();
            // customer_return_id ditautkan ke customer_returns (T15). Nullable no FK.
            $table->unsignedBigInteger('customer_return_id')->nullable();

            // PDF
            $table->string('pdf_path', 255)->nullable();
            $table->timestamp('pdf_generated_at')->nullable();

            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('sales_order_id');
            $table->index('customer_id');
            $table->index('driver_id');
            $table->index('vehicle_id');
        });

        // Partition ditunda ke T20 (sama dengan PO/SO/GRN).
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_orders');
    }
};

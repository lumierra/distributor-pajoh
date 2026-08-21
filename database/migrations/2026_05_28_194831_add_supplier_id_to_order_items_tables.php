<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['so_items', 'do_items', 'invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('suppliers')
                    ->nullOnDelete();
                $table->index('supplier_id', "{$tableName}_supplier_id_index");
            });
        }

        // Backfill: pakai primary supplier untuk tiap produk yg ada di items
        $this->backfill('so_items');
        $this->backfill('do_items');
        $this->backfill('invoice_items');
    }

    private function backfill(string $table): void
    {
        // Sumber map supplier per produk berasal dari pivot supplier_products
        // yang di-drop pada migrasi berikutnya. Pada fresh migrate tabel item
        // masih kosong sehingga backfill jadi no-op; kalau pivot sudah tidak
        // ada (mis. re-run parsial), langsung berhenti.
        if (! Schema::hasTable('supplier_products')) {
            return;
        }

        // Bangun map product_id => primary supplier_id sekali
        $primaryMap = DB::table('supplier_products')
            ->where('is_primary', true)
            ->where('is_active', true)
            ->pluck('supplier_id', 'product_id');

        $fallbackMap = DB::table('supplier_products')
            ->where('is_active', true)
            ->orderBy('product_id')
            ->orderByDesc('id')
            ->get(['product_id', 'supplier_id'])
            ->groupBy('product_id')
            ->map(fn ($rows) => $rows->first()->supplier_id);

        DB::table($table)
            ->whereNull('supplier_id')
            ->select('id', 'product_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $primaryMap, $fallbackMap): void {
                foreach ($rows as $row) {
                    $supplierId = $primaryMap[$row->product_id] ?? $fallbackMap[$row->product_id] ?? null;
                    if ($supplierId) {
                        DB::table($table)->where('id', $row->id)->update(['supplier_id' => $supplierId]);
                    }
                }
            });
    }

    public function down(): void
    {
        foreach (['so_items', 'do_items', 'invoice_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign(["{$tableName}_supplier_id_foreign"]);
                $table->dropIndex("{$tableName}_supplier_id_index");
                $table->dropColumn('supplier_id');
            });
        }
    }
};

<?php

namespace App\Services\Purchasing;

use App\Models\ProductBatch;
use Illuminate\Support\Facades\DB;

/**
 * Resolve atau buat ProductBatch berdasar tuple unik
 * (product_id, supplier_id, batch_code, production_date).
 *
 * Pakai lockForUpdate untuk hindari race condition saat multi-GRN posting
 * concurrent dengan batch yang sama.
 */
class BatchResolver
{
    /**
     * @param  array{
     *   product_id: int,
     *   supplier_id: int|null,
     *   batch_code: string,
     *   production_date?: string|null,
     *   expired_date?: string|null,
     * }  $data
     */
    public function resolveOrCreate(array $data): ProductBatch
    {
        return DB::transaction(function () use ($data): ProductBatch {
            $existing = ProductBatch::query()
                ->where('product_id', $data['product_id'])
                ->where('supplier_id', $data['supplier_id'] ?? null)
                ->where('batch_code', $data['batch_code'])
                ->where('production_date', $data['production_date'] ?? null)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if ($existing->last_received_at !== null) {
                    $existing->last_received_at = now();
                    $existing->save();
                }

                return $existing;
            }

            return ProductBatch::create([
                'product_id' => $data['product_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'batch_code' => $data['batch_code'],
                'production_date' => $data['production_date'] ?? null,
                'expired_date' => $data['expired_date'] ?? null,
                'initial_qty_base' => 0,
                'first_received_at' => now(),
                'last_received_at' => now(),
                'is_active' => true,
            ]);
        });
    }
}

<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\StockBalance;
use App\Models\StockLedger;

/**
 * Pilih batch untuk memenuhi qty yang diminta, urutan FIFO:
 *   1. (bila request bonus) bonus_pool dulu
 *   2. expired_date ASC, NULL paling akhir
 *   3. first_received_at ASC (via batch.created_at sebagai proxy)
 *
 * Expired batch (expired_date < hari ini) di-SKIP — harus write-off via
 * adjustment dulu.
 */
class FifoPicker
{
    /**
     * @return array<int, array{batch_id:int, qty:int, cost_price:float, is_bonus_pool:bool}>
     *
     * @throws InsufficientStockException
     */
    public function pick(int $productId, int $qtyBase, bool $preferBonus = false): array
    {
        $today = now()->toDateString();

        $query = StockBalance::query()
            ->join('product_batches', 'product_batches.id', '=', 'stock_balances.batch_id')
            ->where('stock_balances.product_id', $productId)
            ->where('stock_balances.qty_on_hand', '>', 0)
            ->where(function ($q) use ($today): void {
                $q->whereNull('product_batches.expired_date')
                    ->orWhereDate('product_batches.expired_date', '>=', $today);
            })
            ->where('product_batches.is_active', true);

        if ($preferBonus) {
            $query->orderByRaw('stock_balances.qty_bonus_pool DESC');
        }

        // FIFO ordering: NULL expired_date paling akhir (yang punya tanggal
        // expired wajib diprioritaskan), lalu expired_date ASC, lalu
        // created_at sebagai tie-breaker.
        // `expired_date IS NULL` di MySQL menghasilkan 0/1; ASC → 0 dulu (non-null).
        $balances = $query
            ->orderByRaw('(product_batches.expired_date IS NULL) ASC')
            ->orderBy('product_batches.expired_date')
            ->orderBy('product_batches.created_at')
            ->select([
                'stock_balances.batch_id',
                'stock_balances.qty_on_hand',
                'stock_balances.qty_reserved',
                'stock_balances.qty_bonus_pool',
            ])
            ->get();

        $picks = [];
        $remaining = $qtyBase;
        $totalAvailable = 0;

        foreach ($balances as $b) {
            $available = (int) $b->qty_on_hand - (int) $b->qty_reserved;
            $totalAvailable += max(0, $available);

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);
            if ($take <= 0) {
                continue;
            }

            $picks[] = [
                'batch_id' => (int) $b->batch_id,
                'qty' => $take,
                'cost_price' => $this->batchCost((int) $b->batch_id),
                'is_bonus_pool' => $preferBonus && (int) $b->qty_bonus_pool > 0,
            ];

            $remaining -= $take;
            if ($remaining <= 0) {
                break;
            }
        }

        if ($remaining > 0) {
            throw new InsufficientStockException(
                productId: $productId,
                requested: $qtyBase,
                available: $totalAvailable,
            );
        }

        return $picks;
    }

    /**
     * Ambil cost batch dari last purchase_in ledger (proxy COGS). Kalau tidak
     * ada (mis. batch dari adjustment_in saja), return 0.
     */
    private function batchCost(int $batchId): float
    {
        $cost = StockLedger::query()
            ->where('batch_id', $batchId)
            ->where('type', StockLedger::TYPE_PURCHASE_IN)
            ->latest('created_at')
            ->value('cost_price');

        return (float) ($cost ?? 0);
    }
}

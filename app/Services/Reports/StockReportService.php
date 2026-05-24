<?php

namespace App\Services\Reports;

use App\Models\DailyStockPosition;
use App\Models\StockBalance;
use App\Models\StockLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily stock position snapshot dari stock_balances + avg_cost dari stock_ledger.
 */
class StockReportService
{
    public function generateSnapshot(Carbon $date): int
    {
        $dateStr = $date->toDateString();

        // Avg cost per (product, batch) dari ledger
        $avgCosts = DB::table('stock_ledger')
            ->where('qty_in', '>', 0)
            ->whereIn('type', [
                StockLedger::TYPE_PURCHASE_IN,
                StockLedger::TYPE_RETURN_IN,
                StockLedger::TYPE_ADJUSTMENT_IN,
            ])
            ->select(
                'product_id',
                'batch_id',
                DB::raw('SUM(cost_price * qty_in) / NULLIF(SUM(qty_in), 0) AS avg_cost'),
            )
            ->groupBy('product_id', 'batch_id')
            ->get()
            ->keyBy(fn ($r) => $r->product_id.'-'.($r->batch_id ?? 0));

        $balances = StockBalance::query()->get();

        $count = 0;
        foreach ($balances as $b) {
            $key = $b->product_id.'-'.($b->batch_id ?? 0);
            $avgCost = (float) ($avgCosts[$key]->avg_cost ?? 0);
            $value = $avgCost * (int) $b->qty_on_hand;

            $daysSince = null;
            if ($b->last_movement_at !== null) {
                $daysSince = (int) Carbon::parse($b->last_movement_at)->diffInDays($date);
            }

            DailyStockPosition::query()->updateOrCreate(
                [
                    'snapshot_date' => $dateStr,
                    'product_id' => $b->product_id,
                    'batch_id' => $b->batch_id,
                ],
                [
                    'qty_on_hand_base' => (int) $b->qty_on_hand,
                    'qty_reserved_base' => (int) $b->qty_reserved,
                    'qty_bonus_pool_base' => (int) $b->qty_bonus_pool,
                    'avg_cost' => $avgCost,
                    'stock_value' => round($value, 2),
                    'days_since_last_movement' => $daysSince,
                    'created_at' => now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getSummary(array $filters = []): array
    {
        $date = ! empty($filters['date']) ? $filters['date'] : now()->toDateString();

        $row = DailyStockPosition::query()
            ->whereDate('snapshot_date', $date)
            ->selectRaw('
                COUNT(DISTINCT product_id) AS products,
                COALESCE(SUM(qty_on_hand_base), 0) AS qty,
                COALESCE(SUM(stock_value), 0) AS total_value,
                SUM(CASE WHEN qty_on_hand_base = 0 THEN 1 ELSE 0 END) AS empty_batches,
                SUM(CASE WHEN days_since_last_movement > 60 THEN 1 ELSE 0 END) AS stale_batches
            ')
            ->first();

        return [
            'snapshot_date' => $date,
            'products' => (int) ($row->products ?? 0),
            'qty_total' => (int) ($row->qty ?? 0),
            'total_value' => (float) ($row->total_value ?? 0),
            'empty_batches' => (int) ($row->empty_batches ?? 0),
            'stale_batches' => (int) ($row->stale_batches ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, DailyStockPosition>
     */
    public function getTable(array $filters = []): Collection
    {
        $date = ! empty($filters['date']) ? $filters['date'] : now()->toDateString();

        $q = DailyStockPosition::query()
            ->with(['product:id,name,sku', 'batch:id,batch_code,expired_date'])
            ->whereDate('snapshot_date', $date)
            ->orderByDesc('stock_value');

        if (! empty($filters['product_id'])) {
            $q->where('product_id', $filters['product_id']);
        }

        return $q->limit(500)->get();
    }
}

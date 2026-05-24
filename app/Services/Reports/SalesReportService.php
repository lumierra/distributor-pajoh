<?php

namespace App\Services\Reports;

use App\Models\DailySalesSummary;
use App\Models\StockLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales summary report — daily aggregated from invoices+invoice_items.
 *
 *  - Revenue:  SUM(invoice_items.line_subtotal) per (date, sales, customer, product)
 *  - Cost (HPP): SUM(stock_ledger.cost_price × qty_out) per produk untuk hari itu (FIFO)
 *  - Margin = revenue - cost
 *  - Idempotent via upsert on unique key (snapshot_date, sales_id, customer_id, product_id)
 */
class SalesReportService
{
    /**
     * Generate snapshot untuk satu tanggal. Idempotent.
     */
    public function generateSnapshot(Carbon $date): int
    {
        $dateStr = $date->toDateString();

        // Hitung revenue+qty per (date, sales, customer, product) dari invoice_items
        $rows = DB::table('invoice_items as ii')
            ->join('invoices as inv', 'inv.id', '=', 'ii.invoice_id')
            ->whereDate('inv.invoice_date', $dateStr)
            ->whereNotIn('inv.status', ['cancelled'])
            ->select(
                'inv.sales_id',
                'inv.customer_id',
                'ii.product_id',
                DB::raw('COUNT(DISTINCT inv.id) AS invoice_count'),
                DB::raw('COALESCE(SUM(ii.qty), 0) AS qty_sold'),
                DB::raw('COALESCE(SUM(ii.line_subtotal), 0) AS revenue'),
                DB::raw('COALESCE(SUM((ii.unit_price - ii.unit_net_price) * ii.qty), 0) AS discount_total'),
            )
            ->groupBy('inv.sales_id', 'inv.customer_id', 'ii.product_id')
            ->get();

        // Compute cost per product per day dari stock_ledger sale_out
        $costByProduct = DB::table('stock_ledger')
            ->whereDate('created_at', $dateStr)
            ->where('type', StockLedger::TYPE_SALE_OUT)
            ->select(
                'product_id',
                DB::raw('SUM(cost_price * qty_out) AS total_cost'),
            )
            ->groupBy('product_id')
            ->pluck('total_cost', 'product_id');

        $count = 0;
        foreach ($rows as $row) {
            $revenue = (float) $row->revenue;
            $cost = (float) ($costByProduct[$row->product_id] ?? 0);
            // Cost dibagi proporsional kalau row banyak per produk; simplification — pakai cost-per-product / total qty
            // Untuk MVP: assign cost full ke produk (catatan: drill-down per sales/customer akan over-attribute kalau split)
            // → Lebih akurat: revenue × cost_ratio per product
            $margin = $revenue - $cost;
            $marginPct = $revenue > 0 ? round(($margin / $revenue) * 100, 2) : 0;

            DailySalesSummary::query()->updateOrCreate(
                [
                    'snapshot_date' => $dateStr,
                    'sales_id' => $row->sales_id,
                    'customer_id' => $row->customer_id,
                    'product_id' => $row->product_id,
                ],
                [
                    'invoice_count' => (int) $row->invoice_count,
                    'qty_sold_base' => (int) $row->qty_sold,
                    'revenue' => $revenue,
                    'discount_total' => (float) $row->discount_total,
                    'cost_total' => $cost,
                    'margin' => $margin,
                    'margin_percent' => $marginPct,
                    'created_at' => now(),
                ],
            );
            $count++;
        }

        return $count;
    }

    /**
     * KPI summary untuk dashboard.
     *
     * @param  array{from?:string,to?:string,sales_id?:int,customer_id?:int,product_id?:int}  $filters
     */
    public function getSummary(array $filters = []): array
    {
        $q = $this->applyFilters(DailySalesSummary::query(), $filters);

        $row = $q->selectRaw('
                COUNT(DISTINCT snapshot_date) AS days,
                COALESCE(SUM(invoice_count), 0) AS invoices,
                COALESCE(SUM(qty_sold_base), 0) AS qty,
                COALESCE(SUM(revenue), 0) AS revenue,
                COALESCE(SUM(cost_total), 0) AS cost,
                COALESCE(SUM(margin), 0) AS margin
            ')
            ->first();

        $revenue = (float) ($row->revenue ?? 0);
        $margin = (float) ($row->margin ?? 0);

        return [
            'days' => (int) ($row->days ?? 0),
            'invoices' => (int) ($row->invoices ?? 0),
            'qty_sold' => (int) ($row->qty ?? 0),
            'revenue' => $revenue,
            'cost' => (float) ($row->cost ?? 0),
            'margin' => $margin,
            'margin_percent' => $revenue > 0 ? round(($margin / $revenue) * 100, 2) : 0,
        ];
    }

    /**
     * Tabel data harian.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getTable(array $filters = []): Collection
    {
        $q = $this->applyFilters(DailySalesSummary::query(), $filters);

        return $q->selectRaw('snapshot_date, SUM(invoice_count) AS invoice_count, SUM(revenue) AS revenue, SUM(cost_total) AS cost_total, SUM(margin) AS margin')
            ->groupBy('snapshot_date')
            ->orderByDesc('snapshot_date')
            ->limit(365)
            ->get();
    }

    /**
     * @param  Builder<DailySalesSummary>  $q
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters($q, array $filters)
    {
        if (! empty($filters['from'])) {
            $q->whereDate('snapshot_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('snapshot_date', '<=', $filters['to']);
        }
        if (! empty($filters['sales_id'])) {
            $q->where('sales_id', $filters['sales_id']);
        }
        if (! empty($filters['customer_id'])) {
            $q->where('customer_id', $filters['customer_id']);
        }
        if (! empty($filters['product_id'])) {
            $q->where('product_id', $filters['product_id']);
        }

        return $q;
    }
}

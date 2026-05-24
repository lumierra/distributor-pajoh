<?php

namespace App\Services\Reports;

use App\Models\DailySalesSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Margin report — pakai data dari daily_sales_summaries (sudah include cost_total).
 * Distinct angle: ranking per product/customer by margin.
 */
class MarginReportService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function getSummary(array $filters = []): array
    {
        $q = $this->applyFilters(DailySalesSummary::query(), $filters);
        $row = $q->selectRaw('
                COALESCE(SUM(revenue), 0) AS revenue,
                COALESCE(SUM(cost_total), 0) AS cost,
                COALESCE(SUM(margin), 0) AS margin
            ')->first();

        $revenue = (float) ($row->revenue ?? 0);
        $margin = (float) ($row->margin ?? 0);

        return [
            'revenue' => $revenue,
            'cost' => (float) ($row->cost ?? 0),
            'margin' => $margin,
            'margin_percent' => $revenue > 0 ? round(($margin / $revenue) * 100, 2) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getByProduct(array $filters = []): Collection
    {
        $q = $this->applyFilters(DailySalesSummary::query(), $filters);

        return $q->selectRaw('
                product_id,
                COALESCE(SUM(revenue), 0) AS revenue,
                COALESCE(SUM(cost_total), 0) AS cost,
                COALESCE(SUM(margin), 0) AS margin
            ')
            ->groupBy('product_id')
            ->orderByDesc('margin')
            ->with('product:id,name,sku')
            ->limit(100)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getByCustomer(array $filters = []): Collection
    {
        $q = $this->applyFilters(DailySalesSummary::query(), $filters);

        return $q->selectRaw('
                customer_id,
                COALESCE(SUM(revenue), 0) AS revenue,
                COALESCE(SUM(margin), 0) AS margin
            ')
            ->groupBy('customer_id')
            ->orderByDesc('margin')
            ->with('customer:id,code,name')
            ->limit(100)
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

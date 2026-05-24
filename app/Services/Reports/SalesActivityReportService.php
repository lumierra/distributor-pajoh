<?php

namespace App\Services\Reports;

use App\Models\SalesActivitySnapshot;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales activity report — visit metrics belum tersedia (T05 visits di-defer),
 * sementara metric SO sudah lengkap.
 */
class SalesActivityReportService
{
    public function generateSnapshot(Carbon $date): int
    {
        $dateStr = $date->toDateString();

        // Per-sales aggregation dari sales_orders
        $approved = SalesOrder::STATUS_APPROVED;
        $cancelled = SalesOrder::STATUS_CANCELLED;
        $rows = DB::table('sales_orders')
            ->whereDate('so_date', $dateStr)
            ->whereNotNull('sales_id')
            ->select('sales_id')
            ->selectRaw('COUNT(*) AS so_count')
            ->selectRaw('COALESCE(SUM(total), 0) AS so_value')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS so_approved_count', [$approved])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS so_cancelled_count', [$cancelled])
            ->groupBy('sales_id')
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $totalVisits = 0; // visit tracking di-defer (T05)
            $conversionRate = $totalVisits > 0 ? round(((int) $row->so_count / $totalVisits) * 100, 2) : 0;

            SalesActivitySnapshot::query()->updateOrCreate(
                [
                    'snapshot_date' => $dateStr,
                    'sales_id' => $row->sales_id,
                ],
                [
                    'total_visits' => $totalVisits,
                    'valid_visits' => 0,
                    'unique_customers_visited' => 0,
                    'total_visit_duration_min' => 0,
                    'so_count' => (int) $row->so_count,
                    'so_value' => (float) $row->so_value,
                    'so_approved_count' => (int) $row->so_approved_count,
                    'so_cancelled_count' => (int) $row->so_cancelled_count,
                    'conversion_rate' => $conversionRate,
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
        $q = $this->applyFilters(SalesActivitySnapshot::query(), $filters);
        $row = $q->selectRaw('
                COUNT(DISTINCT sales_id) AS sales,
                COALESCE(SUM(so_count), 0) AS so_count,
                COALESCE(SUM(so_value), 0) AS so_value,
                COALESCE(SUM(so_approved_count), 0) AS so_approved,
                COALESCE(SUM(so_cancelled_count), 0) AS so_cancelled
            ')->first();

        return [
            'sales' => (int) ($row->sales ?? 0),
            'so_count' => (int) ($row->so_count ?? 0),
            'so_value' => (float) ($row->so_value ?? 0),
            'so_approved' => (int) ($row->so_approved ?? 0),
            'so_cancelled' => (int) ($row->so_cancelled ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function getBySales(array $filters = []): Collection
    {
        $q = $this->applyFilters(SalesActivitySnapshot::query(), $filters);

        return $q->selectRaw('
                sales_id,
                COALESCE(SUM(so_count), 0) AS so_count,
                COALESCE(SUM(so_value), 0) AS so_value,
                COALESCE(SUM(so_approved_count), 0) AS so_approved,
                COALESCE(SUM(so_cancelled_count), 0) AS so_cancelled
            ')
            ->with('sales:id,name')
            ->groupBy('sales_id')
            ->orderByDesc('so_value')
            ->limit(100)
            ->get();
    }

    /**
     * @param  Builder<SalesActivitySnapshot>  $q
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

        return $q;
    }
}

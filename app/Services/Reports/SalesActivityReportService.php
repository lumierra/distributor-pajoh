<?php

namespace App\Services\Reports;

use App\Models\SalesActivitySnapshot;
use App\Models\SalesOrder;
use App\Models\SalesVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sales activity report — combine visit metrics (T10) dgn SO metrics.
 *
 *  - Total/valid visits + unique customers + total duration dari sales_visits
 *  - SO count/value/approved/cancelled dari sales_orders
 *  - Conversion rate = (so_count / total_visits) * 100
 */
class SalesActivityReportService
{
    public function generateSnapshot(Carbon $date): int
    {
        $dateStr = $date->toDateString();

        // Per-sales SO aggregation
        $approved = SalesOrder::STATUS_APPROVED;
        $cancelled = SalesOrder::STATUS_CANCELLED;
        $soRows = DB::table('sales_orders')
            ->whereDate('so_date', $dateStr)
            ->whereNotNull('sales_id')
            ->select('sales_id')
            ->selectRaw('COUNT(*) AS so_count')
            ->selectRaw('COALESCE(SUM(total), 0) AS so_value')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS so_approved_count', [$approved])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS so_cancelled_count', [$cancelled])
            ->groupBy('sales_id')
            ->get()
            ->keyBy('sales_id');

        // Per-sales visit aggregation (T10)
        $visitRows = DB::table('sales_visits')
            ->whereDate('checked_in_at', $dateStr)
            ->whereNotIn('status', [SalesVisit::STATUS_CANCELLED])
            ->select('sales_id')
            ->selectRaw('COUNT(*) AS total_visits')
            ->selectRaw('SUM(CASE WHEN bypass_geofence = 0 AND is_mock_location = 0 THEN 1 ELSE 0 END) AS valid_visits')
            ->selectRaw('COUNT(DISTINCT customer_id) AS unique_customers')
            ->selectRaw('COALESCE(SUM(duration_minutes), 0) AS total_duration')
            ->groupBy('sales_id')
            ->get()
            ->keyBy('sales_id');

        $allSalesIds = $soRows->keys()->merge($visitRows->keys())->unique();

        $count = 0;
        foreach ($allSalesIds as $salesId) {
            $so = $soRows->get($salesId);
            $visit = $visitRows->get($salesId);

            $totalVisits = (int) ($visit->total_visits ?? 0);
            $soCount = (int) ($so->so_count ?? 0);
            $conversionRate = $totalVisits > 0
                ? round(($soCount / $totalVisits) * 100, 2)
                : 0;

            SalesActivitySnapshot::query()->updateOrCreate(
                [
                    'snapshot_date' => $dateStr,
                    'sales_id' => $salesId,
                ],
                [
                    'total_visits' => $totalVisits,
                    'valid_visits' => (int) ($visit->valid_visits ?? 0),
                    'unique_customers_visited' => (int) ($visit->unique_customers ?? 0),
                    'total_visit_duration_min' => (int) ($visit->total_duration ?? 0),
                    'so_count' => $soCount,
                    'so_value' => (float) ($so->so_value ?? 0),
                    'so_approved_count' => (int) ($so->so_approved_count ?? 0),
                    'so_cancelled_count' => (int) ($so->so_cancelled_count ?? 0),
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
                COALESCE(SUM(total_visits), 0) AS total_visits,
                COALESCE(SUM(valid_visits), 0) AS valid_visits,
                COALESCE(SUM(so_count), 0) AS so_count,
                COALESCE(SUM(so_value), 0) AS so_value,
                COALESCE(SUM(so_approved_count), 0) AS so_approved,
                COALESCE(SUM(so_cancelled_count), 0) AS so_cancelled,
                COALESCE(AVG(conversion_rate), 0) AS avg_conversion
            ')->first();

        return [
            'sales' => (int) ($row->sales ?? 0),
            'total_visits' => (int) ($row->total_visits ?? 0),
            'valid_visits' => (int) ($row->valid_visits ?? 0),
            'so_count' => (int) ($row->so_count ?? 0),
            'so_value' => (float) ($row->so_value ?? 0),
            'so_approved' => (int) ($row->so_approved ?? 0),
            'so_cancelled' => (int) ($row->so_cancelled ?? 0),
            'avg_conversion' => round((float) ($row->avg_conversion ?? 0), 2),
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
                COALESCE(SUM(total_visits), 0) AS total_visits,
                COALESCE(SUM(valid_visits), 0) AS valid_visits,
                COALESCE(SUM(unique_customers_visited), 0) AS unique_customers,
                COALESCE(SUM(so_count), 0) AS so_count,
                COALESCE(SUM(so_value), 0) AS so_value,
                COALESCE(SUM(so_approved_count), 0) AS so_approved,
                COALESCE(SUM(so_cancelled_count), 0) AS so_cancelled,
                COALESCE(AVG(conversion_rate), 0) AS conversion_rate
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

<?php

namespace App\Services\Reports;

use App\Models\ArAgingSnapshot;
use App\Services\Payment\ArAgingSnapshotService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * AR Aging report — reuses ar_aging_snapshots (T14).
 */
class ArAgingReportService
{
    public function __construct(private readonly ArAgingSnapshotService $service) {}

    public function generateSnapshot(Carbon $date): int
    {
        return $this->service->generateFor($date);
    }

    /**
     * @param  array{date?:string, customer_id?:int}  $filters
     */
    public function getSummary(array $filters = []): array
    {
        $date = ! empty($filters['date']) ? $filters['date'] : now()->toDateString();

        $row = ArAgingSnapshot::query()
            ->whereDate('snapshot_date', $date)
            ->selectRaw('
                COUNT(*) AS customers,
                COALESCE(SUM(bucket_0_30), 0) AS b0,
                COALESCE(SUM(bucket_31_60), 0) AS b1,
                COALESCE(SUM(bucket_61_90), 0) AS b2,
                COALESCE(SUM(bucket_over_90), 0) AS b3,
                COALESCE(SUM(total_outstanding), 0) AS total
            ')
            ->first();

        return [
            'snapshot_date' => $date,
            'customers' => (int) ($row->customers ?? 0),
            'bucket_0_30' => (float) ($row->b0 ?? 0),
            'bucket_31_60' => (float) ($row->b1 ?? 0),
            'bucket_61_90' => (float) ($row->b2 ?? 0),
            'bucket_over_90' => (float) ($row->b3 ?? 0),
            'total_outstanding' => (float) ($row->total ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, ArAgingSnapshot>
     */
    public function getTable(array $filters = []): Collection
    {
        $date = ! empty($filters['date']) ? $filters['date'] : now()->toDateString();

        $q = ArAgingSnapshot::query()
            ->with('customer:id,code,name')
            ->whereDate('snapshot_date', $date)
            ->orderByDesc('total_outstanding');

        if (! empty($filters['customer_id'])) {
            $q->where('customer_id', $filters['customer_id']);
        }

        return $q->limit(500)->get();
    }
}

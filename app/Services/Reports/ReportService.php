<?php

namespace App\Services\Reports;

use Illuminate\Support\Carbon;

/**
 * Orchestrator untuk semua snapshot generation. Idempotent via upsert
 * per service.
 */
class ReportService
{
    public function __construct(
        private readonly SalesReportService $sales,
        private readonly StockReportService $stock,
        private readonly SalesActivityReportService $salesActivity,
        private readonly ArAgingReportService $arAging,
    ) {}

    /**
     * Regenerate semua snapshot untuk tanggal tertentu.
     *
     * @return array<string, int> Count per report type.
     */
    public function regenerateForDate(Carbon $date): array
    {
        return [
            'sales' => $this->sales->generateSnapshot($date),
            'stock' => $this->stock->generateSnapshot($date),
            'sales_activity' => $this->salesActivity->generateSnapshot($date),
            'ar_aging' => $this->arAging->generateSnapshot($date),
        ];
    }
}

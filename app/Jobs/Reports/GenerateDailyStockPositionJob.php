<?php

namespace App\Jobs\Reports;

use App\Services\Reports\StockReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Carbon;

class GenerateDailyStockPositionJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public function __construct(public ?string $date = null) {}

    public function handle(StockReportService $service): void
    {
        $date = $this->date ? Carbon::parse($this->date) : now();
        $service->generateSnapshot($date);
    }
}

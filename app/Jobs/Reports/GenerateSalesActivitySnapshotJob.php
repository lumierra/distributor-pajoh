<?php

namespace App\Jobs\Reports;

use App\Services\Reports\SalesActivityReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Carbon;

class GenerateSalesActivitySnapshotJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public function __construct(public ?string $date = null) {}

    public function handle(SalesActivityReportService $service): void
    {
        $date = $this->date ? Carbon::parse($this->date) : now()->subDay();
        $service->generateSnapshot($date);
    }
}

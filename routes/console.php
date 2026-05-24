<?php

use App\Jobs\Audit\CleanupOldActivityLogsJob;
use App\Jobs\Reports\CleanupOldReportExportsJob;
use App\Jobs\Reports\GenerateDailySalesSummaryJob;
use App\Jobs\Reports\GenerateDailyStockPositionJob;
use App\Jobs\Reports\GenerateSalesActivitySnapshotJob;
use App\Jobs\Sales\AutoCheckoutEndOfDayJob;
use App\Jobs\Sales\ExpireBypassRequestsJob;
use App\Jobs\Sales\NoVisitAlertJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Report snapshot daily jobs (T17)
Schedule::job(new GenerateDailySalesSummaryJob)->dailyAt('23:00');
Schedule::job(new GenerateDailyStockPositionJob)->dailyAt('23:05');
Schedule::job(new GenerateSalesActivitySnapshotJob)->dailyAt('23:10');
Schedule::job(new CleanupOldReportExportsJob)->weeklyOn(0, '02:00');

// Sales Visit jobs (T10)
Schedule::job(new AutoCheckoutEndOfDayJob)->dailyAt('23:59');
Schedule::job(new NoVisitAlertJob)->dailyAt('12:00');
Schedule::job(new ExpireBypassRequestsJob)->hourly();

// Audit log retention cleanup (T19)
Schedule::job(new CleanupOldActivityLogsJob)->weeklyOn(1, '03:00');

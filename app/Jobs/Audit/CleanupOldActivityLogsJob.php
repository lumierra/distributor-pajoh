<?php

namespace App\Jobs\Audit;

use App\Models\ActivityLog;
use App\Services\Setting\SettingManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;

/**
 * Cleanup activity_logs older than retention period (default 730 days = 2 years).
 * Configurable via setting `audit.log_retention_days`.
 */
class CleanupOldActivityLogsJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public function handle(SettingManager $settings): int
    {
        $retentionDays = (int) $settings->get('audit.log_retention_days', 730);
        if ($retentionDays <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($retentionDays);

        return ActivityLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }
}

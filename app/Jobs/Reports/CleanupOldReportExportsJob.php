<?php

namespace App\Jobs\Reports;

use App\Models\ReportExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Support\Facades\Storage;

class CleanupOldReportExportsJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public function __construct(public int $daysToKeep = 7) {}

    public function handle(): int
    {
        $cutoff = now()->subDays($this->daysToKeep);

        $deletedCount = 0;
        ReportExport::query()
            ->where('exported_at', '<', $cutoff)
            ->whereNotNull('file_path')
            ->chunkById(100, function ($chunk) use (&$deletedCount): void {
                foreach ($chunk as $export) {
                    if ($export->file_path && Storage::disk('local')->exists($export->file_path)) {
                        Storage::disk('local')->delete($export->file_path);
                    }
                    $export->delete();
                    $deletedCount++;
                }
            });

        return $deletedCount;
    }
}

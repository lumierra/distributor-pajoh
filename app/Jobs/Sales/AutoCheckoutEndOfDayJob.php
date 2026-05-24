<?php

namespace App\Jobs\Sales;

use App\Models\SalesVisit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;

class AutoCheckoutEndOfDayJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public function handle(): int
    {
        $count = 0;
        SalesVisit::query()
            ->where('status', SalesVisit::STATUS_ACTIVE)
            ->chunkById(100, function ($chunk) use (&$count): void {
                foreach ($chunk as $visit) {
                    $visit->update([
                        'status' => SalesVisit::STATUS_COMPLETED,
                        'checked_out_at' => now(),
                        'duration_minutes' => max(0, (int) $visit->checked_in_at->diffInMinutes(now())),
                        'auto_checked_out' => true,
                        'auto_checkout_reason' => SalesVisit::AUTO_REASON_END_OF_DAY,
                    ]);
                    $count++;
                }
            });

        return $count;
    }
}

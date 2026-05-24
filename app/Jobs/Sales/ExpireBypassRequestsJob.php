<?php

namespace App\Jobs\Sales;

use App\Services\Sales\BypassService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;

class ExpireBypassRequestsJob implements ShouldQueue
{
    use FoundationQueueable, Queueable;

    public function handle(BypassService $service): int
    {
        return $service->expireStalePending();
    }
}

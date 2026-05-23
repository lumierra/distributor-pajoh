<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Cache;

class PurchaseOrderObserver
{
    public function saved(PurchaseOrder $po): void
    {
        Cache::forget("po:{$po->id}");
    }

    public function deleted(PurchaseOrder $po): void
    {
        Cache::forget("po:{$po->id}");
    }
}

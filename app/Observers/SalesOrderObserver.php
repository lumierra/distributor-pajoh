<?php

namespace App\Observers;

use App\Models\SalesOrder;
use Illuminate\Support\Facades\Cache;

class SalesOrderObserver
{
    public function saved(SalesOrder $so): void
    {
        Cache::forget("so:{$so->id}");
        Cache::forget("customer:{$so->customer_id}:outstanding");
    }
}

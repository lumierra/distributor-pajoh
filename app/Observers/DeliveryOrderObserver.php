<?php

namespace App\Observers;

use App\Models\DeliveryOrder;
use Illuminate\Support\Facades\Cache;

class DeliveryOrderObserver
{
    public function saved(DeliveryOrder $do): void
    {
        Cache::forget("do:{$do->id}");
        Cache::forget("so:{$do->sales_order_id}");
        Cache::forget('dashboard:pending_do:count');
        Cache::forget('dashboard:in_transit_do');
    }
}

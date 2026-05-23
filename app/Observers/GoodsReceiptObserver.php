<?php

namespace App\Observers;

use App\Models\GoodsReceipt;
use Illuminate\Support\Facades\Cache;

class GoodsReceiptObserver
{
    public function saved(GoodsReceipt $grn): void
    {
        Cache::forget("grn:{$grn->id}");
        Cache::forget("po:{$grn->purchase_order_id}");
    }
}

<?php

namespace App\Observers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

class InvoiceObserver
{
    public function saved(Invoice $invoice): void
    {
        Cache::forget("invoice:{$invoice->id}");
        Cache::forget("customer:{$invoice->customer_id}:outstanding");
    }
}

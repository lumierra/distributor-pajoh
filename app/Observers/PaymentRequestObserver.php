<?php

namespace App\Observers;

use App\Models\PaymentRequest;
use Illuminate\Support\Facades\Cache;

class PaymentRequestObserver
{
    public function saved(PaymentRequest $req): void
    {
        Cache::forget('dashboard:pending_payment_requests:count');
        Cache::forget("customer:{$req->customer_id}:outstanding");
    }
}

<?php

namespace App\Observers;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;

class PaymentObserver
{
    public function saved(Payment $payment): void
    {
        Cache::forget("payment:{$payment->id}");
        Cache::forget("customer:{$payment->customer_id}:outstanding");
        Cache::forget('dashboard:pending_giro:count');
    }
}

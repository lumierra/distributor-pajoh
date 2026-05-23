<?php

namespace App\Observers;

use App\Models\Customer;
use Illuminate\Support\Facades\Cache;

class CustomerObserver
{
    public function saved(Customer $customer): void
    {
        Cache::forget('customers:active');
    }

    public function deleted(Customer $customer): void
    {
        Cache::forget('customers:active');
        Cache::forget("customer:{$customer->id}:outstanding");
    }

    public function restored(Customer $customer): void
    {
        Cache::forget('customers:active');
    }
}

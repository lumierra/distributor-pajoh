<?php

namespace App\Observers;

use App\Models\Driver;
use Illuminate\Support\Facades\Cache;

class DriverObserver
{
    public function saved(Driver $driver): void
    {
        Cache::forget('drivers:available');
        Cache::forget("driver:{$driver->id}:status");
    }

    public function deleted(Driver $driver): void
    {
        Cache::forget('drivers:available');
        Cache::forget("driver:{$driver->id}:status");
    }
}

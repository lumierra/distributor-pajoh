<?php

namespace App\Observers;

use App\Models\Vehicle;
use Illuminate\Support\Facades\Cache;

class VehicleObserver
{
    public function saved(Vehicle $vehicle): void
    {
        Cache::forget('vehicles:available');
        Cache::forget("vehicle:{$vehicle->id}:status");
    }

    public function deleted(Vehicle $vehicle): void
    {
        Cache::forget('vehicles:available');
        Cache::forget("vehicle:{$vehicle->id}:status");
    }
}

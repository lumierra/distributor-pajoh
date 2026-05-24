<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Services\Setting\SettingManager;

class GeofenceValidator
{
    public function __construct(private readonly SettingManager $settings) {}

    /**
     * Haversine — distance in meters between two lat/long pairs.
     */
    public function distanceMeter(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }

    /**
     * Returns null kalau OK, atau array {distance, radius} kalau out of range.
     *
     * @return array{distance:int, radius:int}|null
     */
    public function checkAgainstCustomer(Customer $customer, float $lat, float $lng): ?array
    {
        if ((bool) $this->settings->get('sales.geofence.enabled', true) === false) {
            return null;
        }
        if ($customer->latitude === null || $customer->longitude === null) {
            // Customer belum punya koordinat — allow + trigger geo capture (handled by caller)
            return null;
        }

        $radius = (int) $this->settings->get('sales.geofence.radius_meter', 100);
        $distance = (int) round($this->distanceMeter($lat, $lng, (float) $customer->latitude, (float) $customer->longitude));

        if ($distance > $radius) {
            return ['distance' => $distance, 'radius' => $radius];
        }

        return null;
    }
}

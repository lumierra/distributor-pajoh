<?php

namespace App\Concerns;

trait HasGeoCoordinates
{
    public function hasGeo(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function distanceTo(float $lat, float $lng): ?float
    {
        if (! $this->hasGeo()) {
            return null;
        }

        return self::haversine(
            (float) $this->latitude,
            (float) $this->longitude,
            $lat,
            $lng,
        );
    }

    public static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earthRadius * asin(sqrt($a));
    }
}

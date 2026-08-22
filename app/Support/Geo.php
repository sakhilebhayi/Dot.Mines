<?php

namespace App\Support;

/**
 * Shared geodesy helpers. One haversine for the whole codebase -- this
 * formula previously existed as five private copies (Route model, three
 * monitoring jobs, RoutePlanningService), each with its own int/float
 * mixing for the analyzers to complain about.
 */
final class Geo
{
    private const EARTH_RADIUS_KM = 6371.0;

    /**
     * Great-circle distance between two coordinates, in kilometres.
     */
    public static function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2.0) * sin($dLat / 2.0)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2.0) * sin($dLon / 2.0);

        $c = 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }
}

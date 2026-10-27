<?php

namespace App\Services\Analytics;

use MaxMind\Db\Reader;
use Throwable;

class GeoIpCountryResolver
{
    public function countryCode(string $ip): ?string
    {
        $database = config('services.linkpilot.analytics.geoip_database');

        if (
            filter_var($ip, FILTER_VALIDATE_IP) === false
            || ! is_string($database)
            || $database === ''
            || ! is_file($database)
            || ! is_readable($database)
        ) {
            return null;
        }

        try {
            $reader = new Reader($database);

            try {
                $record = $reader->get($ip);
            } finally {
                $reader->close();
            }
        } catch (Throwable) {
            return null;
        }

        if (! is_array($record)) {
            return null;
        }

        $countryCode = $record['country']['iso_code']
            ?? $record['registered_country']['iso_code']
            ?? null;

        if (! is_string($countryCode)) {
            return null;
        }

        $countryCode = strtoupper($countryCode);

        return preg_match('/^[A-Z]{2}$/D', $countryCode) === 1 ? $countryCode : null;
    }
}

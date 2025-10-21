<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ipinfo\ipinfo\IPinfo;

class UserRegionService
{
    public function __construct(protected IPinfo $client)
    {
    }

    public function getCountryCode(?Request $request = null): ?string
    {
        $request = $request ?: request();
        $ipAddress = $request?->ip();

        if (!$ipAddress) {
            return null;
        }

        if ($this->isPrivateIp($ipAddress)) {
            return config('services.ipinfo.default_country', 'ID');
        }

        $cacheKey = 'ip_country_' . $ipAddress;
        $ttl = (int) config('services.ipinfo.cache_ttl', 60 * 60 * 24);

        $country = Cache::remember($cacheKey, $ttl, function () use ($ipAddress) {
            try {
                $details = $this->client->getDetails($ipAddress);
                $country = $details->country ?? null;

                if (is_string($country) && strlen($country) === 2) {
                    return strtoupper($country);
                }
            } catch (\Throwable $exception) {
                Log::warning('Failed to retrieve IP country information', [
                    'ip' => $ipAddress,
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        });

        return $country ?: null;
    }

    public function isIndonesia(?string $countryCode): bool
    {
        return strtoupper((string) $countryCode) === 'ID';
    }

    protected function isPrivateIp(string $ipAddress): bool
    {
        if ($ipAddress === '127.0.0.1' || $ipAddress === '::1') {
            return true;
        }

        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}

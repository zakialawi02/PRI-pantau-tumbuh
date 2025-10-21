<?php

namespace App\Services;

use Illuminate\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class LocationService
{
    public function __construct(protected ?CacheRepository $cache = null)
    {
        $defaultStore = Config::get('cache.default');
        $this->cache = $cache ?? Cache::store($defaultStore ?? config('cache.default', 'file'));
    }

    public function getCountryCode(Request $request): ?string
    {
        $ip = $request->ip();

        if (!$ip) {
            return null;
        }

        $cacheKey = sprintf('ipinfo:%s', $ip);

        return $this->cache->remember($cacheKey, now()->addHours(12), function () use ($ip) {
            try {
                if (class_exists(\IPinfo\Laravel\IPinfo::class)) {
                    $details = \IPinfo\Laravel\IPinfo::getDetails($ip);
                } else {
                    $token = env('IPINFO_ACCESS_TOKEN') ?: env('IPINFO_TOKEN');
                    $client = new \IPinfo\IPinfo($token);
                    $details = $client->getDetails($ip);
                }

                if (isset($details->country)) {
                    return strtoupper($details->country);
                }
            } catch (\Throwable $exception) {
                Log::warning('Failed to resolve IP location.', [
                    'ip' => $ip,
                    'message' => $exception->getMessage(),
                ]);
            }

            return null;
        });
    }

    public function isIndonesia(Request $request): bool
    {
        return $this->getCountryCode($request) === 'ID';
    }

    public function getPreferredCurrency(Request $request): string
    {
        if ($this->isIndonesia($request)) {
            return 'IDR';
        }

        return 'USD';
    }

    public function getSupportedCurrencies(): array
    {
        return Config::get('currency.supported', ['IDR', 'USD']);
    }
}

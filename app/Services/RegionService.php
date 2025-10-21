<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use IpInfo\IpInfo;
use Throwable;

class RegionService
{
    protected ?IpInfo $client;

    public function __construct(?IpInfo $client = null)
    {
        $this->client = $client;

        if ($this->client === null) {
            $token = config('services.currency.ipinfo_token');

            try {
                $this->client = $token ? new IpInfo($token) : new IpInfo();
            } catch (Throwable $exception) {
                Log::debug('Failed to initialise IpInfo client', [
                    'message' => $exception->getMessage(),
                ]);

                $this->client = null;
            }
        }
    }

    /**
     * Resolve the region context for the given request.
     */
    public function resolve(Request $request): array
    {
        $defaultCountry = strtoupper((string) config('services.currency.fallback_country', 'US'));
        $defaultCurrency = strtoupper((string) config('services.currency.fallback_currency', 'USD'));

        $country = $this->countryFromHeaders($request) ?? $defaultCountry;
        $currency = $this->mapCountryToCurrency($country) ?? $defaultCurrency;

        if ($country === $defaultCountry) {
            $ip = $this->extractIp($request);

            if ($ip && $this->isPublicIp($ip)) {
                $details = $this->lookupCountryByIp($ip);

                if ($details !== null) {
                    $country = $details;
                    $currency = $this->mapCountryToCurrency($country) ?? $defaultCurrency;
                }
            }
        } else {
            $ip = $this->extractIp($request);
        }

        return [
            'ip' => $ip ?? null,
            'country_code' => $country,
            'currency' => $currency,
            'is_indonesia' => $country === 'ID',
        ];
    }

    /**
     * Determine which payment methods should be shown for the given region context.
     */
    public function paymentMethodsForRegion(array $region): array
    {
        $methods = Arr::get($region, 'is_indonesia') ? ['bank_transfer'] : ['paypal'];

        $methods[] = 'stripe';
        $methods[] = 'manual';

        return array_values(array_unique($methods));
    }

    protected function extractIp(Request $request): ?string
    {
        $headers = [
            'CF-Connecting-IP',
            'CF_CONNECTING_IP',
            'X-Forwarded-For',
            'X_FORWARDED_FOR',
            'X-Real-IP',
            'X_REAL_IP',
        ];

        foreach ($headers as $header) {
            $value = $request->headers->get($header) ?? $request->server($header);
            if ($value) {
                $ip = trim(explode(',', $value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        $ip = $request->ip();
        return $ip && filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    protected function isPublicIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    protected function countryFromHeaders(Request $request): ?string
    {
        $candidates = [
            $request->headers->get('CF-IPCountry'),
            $request->server('HTTP_CF_IPCOUNTRY'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate) {
                $candidate = strtoupper(trim($candidate));
                if (preg_match('/^[A-Z]{2}$/', $candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    protected function lookupCountryByIp(string $ip): ?string
    {
        if ($this->client === null) {
            return null;
        }

        $cacheKey = 'region:' . $ip;
        $cacheTtl = (int) config('services.currency.ip_cache_seconds', 86400);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($ip) {
            try {
                $details = $this->client->getDetails($ip);
                $country = strtoupper((string) ($details->country ?? ''));

                return $country !== '' ? $country : null;
            } catch (Throwable $exception) {
                Log::debug('Failed to resolve IP country', [
                    'message' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    protected function mapCountryToCurrency(string $countryCode): ?string
    {
        $countryCode = strtoupper($countryCode);
        $mapping = config('services.currency.country_currency_map', []);

        if (isset($mapping[$countryCode])) {
            return strtoupper($mapping[$countryCode]);
        }

        if ($countryCode === 'ID') {
            return 'IDR';
        }

        return strtoupper((string) config('services.currency.fallback_currency', 'USD'));
    }
}

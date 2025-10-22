<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UserRegionResolver
{
    private const DEFAULT_FALLBACK_COUNTRY = 'US';
    private const INDONESIA_COUNTRY_CODE = 'ID';

    public function resolveCountry(?string $ip = null): string
    {
        $ipAddress = $ip ?: request()->ip();

        if ($this->isLocalAddress($ipAddress)) {
            return strtoupper(config('services.ipinfo.default_country', self::INDONESIA_COUNTRY_CODE));
        }

        $cacheTtl = now()->addSeconds((int) config('services.ipinfo.cache_ttl', 86400));
        $cacheKey = sprintf('ipinfo:country:%s', $ipAddress);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($ipAddress) {
            return $this->fetchCountryFromIpinfo($ipAddress)
                ?? strtoupper(config('services.ipinfo.fallback_country', self::DEFAULT_FALLBACK_COUNTRY));
        });
    }

    public function preferredCurrency(?string $ip = null): string
    {
        $country = $this->resolveCountry($ip);

        return $this->currencyForCountry($country);
    }

    public function currencyForCountry(?string $countryCode): string
    {
        $country = strtoupper($countryCode ?? '');

        if ($country === self::INDONESIA_COUNTRY_CODE) {
            return 'IDR';
        }

        return strtoupper(config('services.ipinfo.fallback_currency', 'USD'));
    }

    public function determinePaymentPreferences(Request $request): array
    {
        $country = $this->resolveCountry($request->ip());
        $currency = $this->currencyForCountry($country);

        $methods = ['manual'];
        if ($currency === 'IDR') {
            $methods[] = 'bank_transfer';
        } else {
            $methods[] = 'paypal';
        }

        if (config('services.stripe.key')) {
            $methods[] = 'stripe';
        }

        $methods = array_values(array_unique(array_merge($methods, ['manual'])));
        $primary = $currency === 'IDR' ? 'bank_transfer' : 'paypal';

        if (!in_array($primary, $methods, true)) {
            array_unshift($methods, $primary);
        } else {
            $methods = array_values(array_unique(array_merge([$primary], $methods)));
        }

        return [
            'country' => $country,
            'currency' => $currency,
            'methods' => $methods,
            'default_method' => $methods[0] ?? 'manual',
        ];
    }

    private function fetchCountryFromIpinfo(string $ip): ?string
    {
        try {
            $token = config('services.ipinfo.token');
            $request = Http::timeout(5)->acceptJson();

            if (!empty($token)) {
                $request = $request->withToken($token);
            }

            $response = $request->get(sprintf('https://ipinfo.io/%s/json', $ip));

            if ($response->failed()) {
                Log::warning('IPInfo request failed', [
                    'ip' => $ip,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $country = strtoupper((string) data_get($response->json(), 'country', ''));

            return $country ?: null;
        } catch (\Throwable $exception) {
            Log::warning('Unable to resolve IP country via ipinfo', [
                'ip' => $ip,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function isLocalAddress(?string $ip): bool
    {
        return in_array($ip, ['127.0.0.1', '::1', null], true);
    }
}

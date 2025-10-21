<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Ipinfo\Ipinfo;

class RegionService
{
    protected string $sessionKey = 'detected_country';

    public function resolveCountryCode(?Request $request = null): ?string
    {
        $request ??= request();

        if (!$request) {
            return null;
        }

        if ($request->hasSession() && $request->session()->has($this->sessionKey)) {
            $stored = $request->session()->get($this->sessionKey);
            if (is_string($stored) && $stored !== '') {
                return strtoupper($stored);
            }
        }

        $country = $this->extractCountryFromRequest($request);
        if ($country) {
            $this->storeCountryInSession($request, $country);

            return $country;
        }

        $ip = $this->extractIpFromRequest($request);
        if (!$ip) {
            return null;
        }

        $cacheKey = sprintf('ip-country:%s', $ip);

        $country = Cache::remember($cacheKey, (int) config('services.ipinfo.cache_ttl', 3600), function () use ($ip) {
            return $this->lookupCountryByIp($ip);
        });

        if ($country) {
            $this->storeCountryInSession($request, $country);
        }

        return $country ? strtoupper($country) : null;
    }

    public function preferredCurrency(?string $countryCode): string
    {
        return $this->isIndonesia($countryCode) ? 'IDR' : 'USD';
    }

    public function supportsBankTransfer(?string $countryCode): bool
    {
        return $this->isIndonesia($countryCode);
    }

    public function supportsPaypal(?string $countryCode): bool
    {
        return !$this->isIndonesia($countryCode);
    }

    public function isIndonesia(?string $countryCode): bool
    {
        return strtoupper((string) $countryCode) === 'ID';
    }

    protected function extractCountryFromRequest(Request $request): ?string
    {
        $candidates = [
            $request->header('CF-IPCountry'),
            $request->header('X-Country-Code'),
            $request->header('X-App-Country'),
            $request->header('X-Country'),
            $request->header('X-User-Country'),
            $request->header('X-User-Region'),
            $request->query('country'),
            $request->query('region'),
        ];

        if ($user = $request->user()) {
            $userCountry = data_get($user, 'country');
            if ($userCountry) {
                $candidates[] = $userCountry;
            }
        }

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return strtoupper($candidate);
            }
        }

        return null;
    }

    protected function extractIpFromRequest(Request $request): ?string
    {
        $ip = $request->ip();

        if (!is_string($ip) || $ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        return $ip;
    }

    protected function lookupCountryByIp(string $ip): ?string
    {
        $token = config('services.ipinfo.token');

        if ($token === null || $token === '') {
            return null;
        }

        try {
            $client = new Ipinfo($token);
            $details = $client->getDetails($ip);
            $country = $details->country ?? null;

            return is_string($country) && $country !== '' ? strtoupper($country) : null;
        } catch (\Throwable $exception) {
            Log::debug('Unable to resolve country from IP.', [
                'ip' => $ip,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function storeCountryInSession(Request $request, string $countryCode): void
    {
        if ($request->hasSession()) {
            $request->session()->put($this->sessionKey, strtoupper($countryCode));
        }
    }
}

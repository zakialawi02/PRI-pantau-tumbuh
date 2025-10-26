<?php

namespace App\Services;

use Throwable;
use ipinfo\ipinfo\IPinfo;
use Illuminate\Support\Facades\Log;

class UserLocationService
{
    private IPinfo $client;

    public function __construct(?IPinfo $client = null)
    {
        $this->client = $client ?? new IPinfo(config('services.ipinfo.token'));
    }

    public function getCountryCode(?string $ip = null): ?string
    {
        $ip = $ip ?: request()?->ip();

        if (empty($ip) || in_array($ip, ['127.0.0.1', 'localhost', '::1'], true)) {
            return null;
        }

        try {
            $details = $this->client->getDetails($ip);
            if (isset($details->country) && $details->country) {
                return strtoupper($details->country);
            }
        } catch (Throwable $exception) {
            Log::debug('Unable to resolve country from IP', [
                'ip' => $ip,
                'message' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    public function resolveCurrency(?string $ip = null): string
    {
        $country = $this->getCountryCode($ip);

        if ($country === 'ID') {
            return 'IDR';
        }

        return 'IDR';
    }

    public function resolvePaymentMethods(?string $ip = null): array
    {
        $currency = $this->resolveCurrency($ip);

        if ($currency === 'IDR') {
            return ['bank_transfer', 'manual'];
        }

        return ['paypal', 'manual'];
    }
}

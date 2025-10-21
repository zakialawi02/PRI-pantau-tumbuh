<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ipinfo\Ipinfo;
use Throwable;

class CurrencyService
{
    public const DEFAULT_BASE_CURRENCY = 'IDR';
    public const SUPPORTED_CURRENCIES = ['IDR', 'USD'];
    private const EXCHANGE_API_ENDPOINT = 'https://latest.currency-api.pages.dev/v1/currencies';

    public function __construct(private ?Ipinfo $ipinfo = null, private ?CacheRepository $cache = null)
    {
        $token = config('services.ipinfo.token');
        $this->ipinfo = $ipinfo;

        if ($this->ipinfo === null && class_exists(Ipinfo::class)) {
            try {
                $this->ipinfo = new Ipinfo($token ?: null);
            } catch (Throwable $exception) {
                Log::warning('Failed to initialise IPinfo client', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        $defaultStore = config('cache.default', 'file');
        $this->cache = $cache ?? Cache::store($defaultStore);
    }

    public function getUserCurrency(?Request $request = null): string
    {
        $country = $this->getUserCountry($request);

        if ($country === 'ID') {
            return 'IDR';
        }

        return 'USD';
    }

    public function isUserFromIndonesia(?Request $request = null): bool
    {
        return $this->getUserCurrency($request) === 'IDR';
    }

    public function getUserCountry(?Request $request = null): ?string
    {
        try {
            $request ??= request();
        } catch (Throwable $exception) {
            $request = null;
        }

        $fallback = strtoupper((string) config('services.ipinfo.fallback_country', 'US'));

        if (!$request) {
            return $fallback;
        }

        $ip = $request->ip();
        if (!$ip || in_array($ip, ['127.0.0.1', '::1'])) {
            return $fallback;
        }

        if (!$this->ipinfo) {
            return $fallback;
        }

        try {
            $details = $this->ipinfo->getDetails($ip);
            $country = strtoupper((string) ($details->country ?? ''));

            if (!empty($country)) {
                return $country;
            }
        } catch (Throwable $exception) {
            Log::warning('Unable to resolve user country via IPinfo', [
                'ip' => $ip,
                'message' => $exception->getMessage(),
            ]);
        }

        return $fallback;
    }

    public function getRate(string $base = 'IDR', string $target = 'USD', bool $forceRefresh = false): float
    {
        $base = strtoupper($base);
        $target = strtoupper($target);

        if ($base === $target) {
            return 1.0;
        }

        $cacheKey = $this->getCacheKey($base, $target);

        if (!$forceRefresh) {
            $cachedRate = $this->cache->get($cacheKey);
            if ($cachedRate) {
                return (float) $cachedRate;
            }
        }

        $rateModel = ExchangeRate::where('base_currency', $base)
            ->where('target_currency', $target)
            ->first();

        $needsRefresh = $forceRefresh;
        if ($rateModel === null) {
            $rateModel = new ExchangeRate([
                'base_currency' => $base,
                'target_currency' => $target,
            ]);
            $needsRefresh = true;
        } elseif (!$forceRefresh) {
            $lastUpdated = $rateModel->last_synced_at ?? $rateModel->updated_at;
            $needsRefresh = !$lastUpdated || $lastUpdated->lt(now()->subWeek());
        }

        if ($needsRefresh) {
            $rate = $this->syncRateFromApi($base, $target, $rateModel);
        } else {
            $rate = (float) $rateModel->rate;
        }

        $this->cache->put($cacheKey, $rate, now()->addDays(7));

        return $rate;
    }

    public function convertAmount(float $amount, string $fromCurrency, string $toCurrency, ?float $preloadedRate = null): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency) {
            return round($amount, 2);
        }

        $rate = $preloadedRate ?? $this->getRate(self::DEFAULT_BASE_CURRENCY, 'USD');

        if ($fromCurrency === 'IDR' && $toCurrency === 'USD') {
            return round($amount * $rate, 2);
        }

        if ($fromCurrency === 'USD' && $toCurrency === 'IDR') {
            $safeRate = $rate > 0 ? $rate : 1;
            return round($amount / $safeRate, 2);
        }

        if ($fromCurrency !== 'IDR') {
            $amountInIdr = $this->convertAmount($amount, $fromCurrency, 'IDR', $rate);
            return $this->convertAmount($amountInIdr, 'IDR', $toCurrency, $rate);
        }

        // $fromCurrency is IDR and target is neither USD nor IDR, fall back to base amount
        return round($amount, 2);
    }

    public function preparePlanForDisplay($plan, string $targetCurrency): void
    {
        $targetCurrency = strtoupper($targetCurrency);
        if (!in_array($targetCurrency, self::SUPPORTED_CURRENCIES, true)) {
            $targetCurrency = 'USD';
        }

        $baseCurrency = strtoupper($plan->currency ?? self::DEFAULT_BASE_CURRENCY);
        $amount = (float) ($plan->price ?? 0);
        $rate = $this->getRate(self::DEFAULT_BASE_CURRENCY, 'USD');

        $displayPrice = $this->convertAmount($amount, $baseCurrency, $targetCurrency, $rate);
        $plan->setAttribute('display_price', $displayPrice);
        $plan->setAttribute('display_currency', $targetCurrency);
        $plan->setAttribute('base_price', $amount);
        $plan->setAttribute('base_currency', $baseCurrency);
        $plan->setAttribute('exchange_rate', $rate);
    }

    public function getCheckoutPricing(float $baseAmount, string $baseCurrency, string $desiredCurrency): array
    {
        $baseCurrency = strtoupper($baseCurrency);
        $desiredCurrency = strtoupper($desiredCurrency);
        $rate = $this->getRate(self::DEFAULT_BASE_CURRENCY, 'USD');

        $amountIdr = $this->convertAmount($baseAmount, $baseCurrency, 'IDR', $rate);
        $amountUsd = $this->convertAmount($baseAmount, $baseCurrency, 'USD', $rate);

        $displayAmount = $desiredCurrency === 'USD' ? $amountUsd : $amountIdr;
        if ($desiredCurrency !== 'USD' && $desiredCurrency !== 'IDR') {
            $desiredCurrency = 'USD';
            $displayAmount = $amountUsd;
        }

        return [
            'price' => $displayAmount,
            'price_currency' => $desiredCurrency,
            'amounts' => [
                'IDR' => $amountIdr,
                'USD' => $amountUsd,
            ],
            'exchange_rate' => $rate,
        ];
    }

    public function getAllowedPaymentMethods(bool $userFromIndonesia): array
    {
        $methods = ['manual'];

        if ($userFromIndonesia) {
            $methods[] = 'bank_transfer';
        } else {
            $methods[] = 'paypal';
        }

        if (config('services.stripe.key')) {
            $methods[] = 'stripe';
        }

        return $methods;
    }

    public function getDefaultPaymentMethod(array $allowedMethods): string
    {
        if (in_array('bank_transfer', $allowedMethods, true)) {
            return 'bank_transfer';
        }

        if (in_array('paypal', $allowedMethods, true)) {
            return 'paypal';
        }

        if (in_array('stripe', $allowedMethods, true)) {
            return 'stripe';
        }

        return $allowedMethods[0] ?? 'paypal';
    }

    public function refreshRates(bool $force = false): float
    {
        return $this->getRate(self::DEFAULT_BASE_CURRENCY, 'USD', $force);
    }

    private function syncRateFromApi(string $base, string $target, ExchangeRate $model): float
    {
        try {
            $response = $this->requestExchangeRate($base, $target);
            $rate = $this->extractRateFromResponse($response, $base, $target);

            $model->fill([
                'rate' => $rate,
                'last_synced_at' => now(),
            ]);

            $model->save();

            return $rate;
        } catch (Throwable $exception) {
            Log::warning('Failed to sync exchange rate from API', [
                'base' => $base,
                'target' => $target,
                'message' => $exception->getMessage(),
            ]);

            if ($model->exists && $model->rate) {
                return (float) $model->rate;
            }

            throw $exception;
        }
    }

    private function requestExchangeRate(string $base, string $target): Response
    {
        $base = strtolower($base);
        $target = strtolower($target);
        $url = sprintf('%s/%s/%s.json', self::EXCHANGE_API_ENDPOINT, $base, $target);

        try {
            $response = Http::timeout(10)->get($url);
        } catch (ConnectionException|RequestException $exception) {
            throw $exception;
        }

        if (!$response->successful()) {
            $response->throw();
        }

        return $response;
    }

    private function extractRateFromResponse(Response $response, string $base, string $target): float
    {
        $data = $response->json();
        $baseKey = strtolower($base);
        $targetKey = strtolower($target);

        $rate = null;
        if (isset($data[$baseKey][$targetKey])) {
            $rate = $data[$baseKey][$targetKey];
        } elseif (isset($data[$targetKey])) {
            $rate = $data[$targetKey];
        }

        $rate = (float) $rate;
        if ($rate <= 0) {
            throw new RequestException($response);
        }

        return $rate;
    }

    private function getCacheKey(string $base, string $target): string
    {
        return sprintf('exchange_rate_%s_%s', strtoupper($base), strtoupper($target));
    }
}

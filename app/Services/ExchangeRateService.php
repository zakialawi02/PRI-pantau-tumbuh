<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ExchangeRateService
{
    private const CACHE_PREFIX = 'exchange_rate';

    public function __construct(private readonly CacheRepository $cache) {}

    /**
     * Retrieve the exchange rate for the given currency pair.
     */
    public function getRate(string $baseCurrency, string $targetCurrency): float
    {
        $baseCurrency = strtoupper($baseCurrency);
        $targetCurrency = strtoupper($targetCurrency);

        if ($baseCurrency === $targetCurrency) {
            return 1.0;
        }

        $cacheKey = $this->cacheKey($baseCurrency, $targetCurrency);
        $ttlSeconds = (int) config('exchange.cache_ttl.seconds', 86400);

        return $this->cache->remember($cacheKey, Carbon::now()->addSeconds($ttlSeconds), function () use ($baseCurrency, $targetCurrency) {
            $record = ExchangeRate::where('base_currency', $baseCurrency)
                ->where('target_currency', $targetCurrency)
                ->first();

            if (!$record || !$record->fetched_at || $record->fetched_at->lte(Carbon::now()->subWeek())) {
                $rate = $this->refreshRate($baseCurrency, $targetCurrency);

                if ($rate !== null) {
                    return $rate;
                }
            }

            if ($record) {
                return (float) $record->rate;
            }

            $fallback = $this->fallbackRate($baseCurrency, $targetCurrency);
            if ($fallback !== null) {
                return $fallback;
            }

            throw new RuntimeException('Unable to resolve exchange rate for currency pair.');
        });
    }

    /**
     * Force refresh the exchange rate for the currency pair from the remote API.
     */
    public function refreshRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $baseCurrency = strtoupper($baseCurrency);
        $targetCurrency = strtoupper($targetCurrency);

        if ($baseCurrency === $targetCurrency) {
            return 1.0;
        }

        $rate = $this->fetchRateFromApi($baseCurrency, $targetCurrency);
        if ($rate === null) {
            $fallback = $this->fallbackRate($baseCurrency, $targetCurrency);
            if ($fallback !== null) {
                $this->storeRate($baseCurrency, $targetCurrency, $fallback, false);
                return $fallback;
            }

            return null;
        }

        $this->storeRate($baseCurrency, $targetCurrency, $rate);

        return $rate;
    }

    /**
     * Convert amount between currencies.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $rate = $this->getRate($fromCurrency, $toCurrency);

        return round($amount * $rate, 1);
    }

    protected function storeRate(string $baseCurrency, string $targetCurrency, float $rate, bool $updateFetchedAt = true): void
    {
        ExchangeRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'target_currency' => $targetCurrency,
            ],
            [
                'rate' => $rate,
                'fetched_at' => $updateFetchedAt ? Carbon::now() : null,
            ]
        );

        $ttlSeconds = (int) config('exchange.cache_ttl.seconds', 86400);
        $this->cache->put(
            $this->cacheKey($baseCurrency, $targetCurrency),
            $rate,
            Carbon::now()->addSeconds($ttlSeconds)
        );
    }

    protected function cacheKey(string $baseCurrency, string $targetCurrency): string
    {
        return sprintf('%s_%s_%s', self::CACHE_PREFIX, $baseCurrency, $targetCurrency);
    }

    protected function fetchRateFromApi(string $baseCurrency, string $targetCurrency): ?float
    {
        $base = strtolower($baseCurrency);
        $target = strtolower($targetCurrency);
        $url = rtrim(config('exchange.api_base_url'), '/') . '/' . $base . '.json';

        try {
            $response = Http::timeout(10)->retry(1, 500)->get($url);

            if ($response->failed()) {
                Log::warning('Failed to fetch exchange rate', [
                    'base' => $baseCurrency,
                    'target' => $targetCurrency,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            if (!is_array($data) || !isset($data[$base][$target])) {
                Log::warning('Exchange rate pair missing from API response', [
                    'base' => $baseCurrency,
                    'target' => $targetCurrency,
                ]);

                return null;
            }

            return (float) $data[$base][$target];
        } catch (Throwable $exception) {
            Log::warning('Exchange rate API request failed', [
                'base' => $baseCurrency,
                'target' => $targetCurrency,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function fallbackRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $key = strtoupper($baseCurrency . '_' . $targetCurrency);
        $fallbackRates = config('exchange.fallback_rates', []);

        return isset($fallbackRates[$key]) ? (float) $fallbackRates[$key] : null;
    }
}

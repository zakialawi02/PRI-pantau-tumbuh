<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExchangeRateService
{
    public const DEFAULT_BASE_CURRENCY = 'IDR';
    public const SUPPORTED_CURRENCIES = ['IDR', 'USD'];

    public function getRate(string $baseCurrency, string $targetCurrency): float
    {
        $base = strtoupper($baseCurrency);
        $target = strtoupper($targetCurrency);

        if ($base === $target) {
            return 1.0;
        }

        if (!in_array($base, self::SUPPORTED_CURRENCIES, true) || !in_array($target, self::SUPPORTED_CURRENCIES, true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported currency conversion requested: %s to %s', $base, $target));
        }

        $cacheKey = $this->cacheKey($base, $target);
        $ttl = now()->addSeconds(config('exchange.cache_ttl', 604800));

        return Cache::remember($cacheKey, $ttl, function () use ($base, $target) {
            $recent = ExchangeRate::where('base_currency', $base)
                ->where('target_currency', $target)
                ->orderByDesc('retrieved_at')
                ->first();

            if ($recent && $recent->retrieved_at instanceof Carbon && $recent->retrieved_at->greaterThanOrEqualTo(now()->subWeek())) {
                return (float) $recent->rate;
            }

            return $this->refreshRate($base, $target);
        });
    }

    public function refreshRate(string $baseCurrency, string $targetCurrency): float
    {
        $base = strtoupper($baseCurrency);
        $target = strtoupper($targetCurrency);

        if ($base === $target) {
            return 1.0;
        }

        $endpoint = rtrim(config('exchange.api_base', 'https://cdn.jsdelivr.net/gh/fawazahmed0/exchange-api@1/latest/currencies'), '/');
        $response = Http::timeout(10)
            ->acceptJson()
            ->retry(2, 250)
            ->get(sprintf('%s/%s/%s.json', $endpoint, strtolower($base), strtolower($target)));

        if ($response->failed()) {
            Log::warning('Exchange rate API request failed', [
                'base' => $base,
                'target' => $target,
                'status' => $response->status(),
            ]);

            return $this->fallbackRate($base, $target);
        }

        $rate = (float) data_get($response->json(), strtolower($target));

        if ($rate <= 0) {
            Log::warning('Exchange rate API returned invalid rate', [
                'base' => $base,
                'target' => $target,
                'payload' => $response->json(),
            ]);

            return $this->fallbackRate($base, $target);
        }

        $this->storeRatePair($base, $target, $rate);

        return $rate;
    }

    public function storeRatePair(string $baseCurrency, string $targetCurrency, float $rate): void
    {
        $base = strtoupper($baseCurrency);
        $target = strtoupper($targetCurrency);
        $timestamp = now();

        ExchangeRate::updateOrCreate(
            ['base_currency' => $base, 'target_currency' => $target],
            ['rate' => $rate, 'retrieved_at' => $timestamp]
        );

        Cache::put($this->cacheKey($base, $target), $rate, now()->addSeconds(config('exchange.cache_ttl', 604800)));

        if ($rate > 0) {
            $inverse = 1 / $rate;
            $inverseBase = $target;
            $inverseTarget = $base;

            ExchangeRate::updateOrCreate(
                ['base_currency' => $inverseBase, 'target_currency' => $inverseTarget],
                ['rate' => $inverse, 'retrieved_at' => $timestamp]
            );

            Cache::put($this->cacheKey($inverseBase, $inverseTarget), $inverse, now()->addSeconds(config('exchange.cache_ttl', 604800)));
        }
    }

    public function calculateRate(?float $amountBase, ?float $amountTarget): ?float
    {
        if ($amountBase === null || $amountTarget === null || $amountTarget == 0.0) {
            return null;
        }

        return round($amountBase / $amountTarget, 8);
    }

    private function fallbackRate(string $baseCurrency, string $targetCurrency): float
    {
        $existing = ExchangeRate::where('base_currency', $baseCurrency)
            ->where('target_currency', $targetCurrency)
            ->orderByDesc('retrieved_at')
            ->first();

        if ($existing) {
            return (float) $existing->rate;
        }

        throw new \RuntimeException(sprintf('Unable to retrieve exchange rate for %s to %s', $baseCurrency, $targetCurrency));
    }

    private function cacheKey(string $baseCurrency, string $targetCurrency): string
    {
        return sprintf('exchange_rate:%s:%s', strtoupper($baseCurrency), strtoupper($targetCurrency));
    }
}

<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CurrencyConverterService
{
    /**
     * Convert an amount from one currency to another.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency === $toCurrency || abs($amount) < 0.00001) {
            return round($amount, 2);
        }

        $rate = $this->getRate($fromCurrency, $toCurrency);

        return round($amount * $rate, 2);
    }

    /**
     * Retrieve a conversion rate for the given currency pair.
     */
    public function getRate(string $baseCurrency, string $targetCurrency): float
    {
        $baseCurrency = strtoupper($baseCurrency);
        $targetCurrency = strtoupper($targetCurrency);

        if ($baseCurrency === $targetCurrency) {
            return 1.0;
        }

        $existing = CurrencyRate::where('base_currency', $baseCurrency)
            ->where('target_currency', $targetCurrency)
            ->first();

        $refreshDays = (int) config('services.currency.refresh_days', 7);
        $shouldRefresh = !$existing || !$existing->retrieved_at || $existing->retrieved_at->lte(now()->subDays($refreshDays));

        if ($shouldRefresh) {
            $fetchedRate = $this->fetchRateFromApi($baseCurrency, $targetCurrency);

            if ($fetchedRate !== null) {
                CurrencyRate::updateOrCreate(
                    [
                        'base_currency' => $baseCurrency,
                        'target_currency' => $targetCurrency,
                    ],
                    [
                        'rate' => $fetchedRate,
                        'retrieved_at' => now(),
                    ]
                );

                return (float) $fetchedRate;
            }
        }

        if ($existing) {
            return (float) $existing->rate;
        }

        $fallback = $this->fallbackRate($baseCurrency, $targetCurrency);
        if ($fallback !== null) {
            return (float) $fallback;
        }

        return 1.0;
    }

    /**
     * Fetch a conversion rate from the configured external API.
     */
    protected function fetchRateFromApi(string $baseCurrency, string $targetCurrency): ?float
    {
        $endpoint = (string) config('services.currency.rate_api');
        if (empty($endpoint)) {
            return null;
        }

        $endpoint = str_replace(['{base}', '{BASE}'], $baseCurrency, $endpoint);
        $endpoint = str_replace(['{target}', '{TARGET}'], $targetCurrency, $endpoint);

        try {
            $response = Http::timeout((int) config('services.currency.request_timeout', 10))
                ->retry((int) config('services.currency.request_retries', 2), 200)
                ->get($endpoint);

            if (!$response->successful()) {
                Log::warning('Currency rate API returned non-success status', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();
            $rate = Arr::get($data, "rates.$targetCurrency");

            if ($rate === null && Arr::has($data, 'conversion_rates')) {
                $rate = Arr::get($data, "conversion_rates.$targetCurrency");
            }

            if ($rate === null && Arr::has($data, 'data')) {
                $rate = Arr::get($data, "data.$targetCurrency");
            }

            if ($rate === null && Arr::has($data, 'result')) {
                $rate = Arr::get($data, "result.$targetCurrency");
            }

            if ($rate === null) {
                return null;
            }

            return (float) $rate;
        } catch (Throwable $exception) {
            Log::warning('Currency rate API request failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Provide a fallback conversion rate if the API is not available.
     */
    protected function fallbackRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $fallbackRates = config('services.currency.default_rates', []);
        $rate = Arr::get($fallbackRates, "$baseCurrency.$targetCurrency");

        if ($rate !== null) {
            return (float) $rate;
        }

        $inverse = Arr::get($fallbackRates, "$targetCurrency.$baseCurrency");
        if ($inverse !== null && (float) $inverse !== 0.0) {
            return (float) (1 / $inverse);
        }

        return null;
    }
}

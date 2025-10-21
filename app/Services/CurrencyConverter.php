<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyConverter
{
    protected const SUPPORTED_CURRENCIES = ['IDR', 'USD'];
    protected string $defaultCurrency = 'IDR';

    /**
     * Convert the given amount to the target currency.
     *
     * @return array{0: float, 1: string}
     */
    public function convert(float $amount, ?string $fromCurrency, string $toCurrency): array
    {
        [$convertedAmount, $currency] = $this->convertWithRate($amount, $fromCurrency, $toCurrency);

        return [$convertedAmount, $currency];
    }

    /**
     * Convert the given amount and return the rate used for conversion.
     *
     * @return array{0: float, 1: string, 2: float|null}
     */
    public function convertWithRate(float $amount, ?string $fromCurrency, string $toCurrency): array
    {
        $source = $this->normalizeCurrency($fromCurrency ?: $this->defaultCurrency);
        $target = $this->normalizeCurrency($toCurrency);

        if ($source === $target) {
            return [round($amount, 2), $target, 1.0];
        }

        $rate = $this->getExchangeRate($source, $target);

        if ($rate === null) {
            return [round($amount, 2), $source, null];
        }

        return [round($amount * $rate, 2), $target, $rate];
    }

    /**
     * Capture the converted amounts for all supported currencies including their rates.
     */
    public function snapshotConversions(float $amount, ?string $fromCurrency = null): array
    {
        $source = $this->normalizeCurrency($fromCurrency ?: $this->defaultCurrency);
        $baseAmount = round($amount, 2);

        $snapshot = [
            'base_amount' => $baseAmount,
            'base_currency' => $source,
            'rates' => [],
            'generated_at' => now()->toIso8601String(),
        ];

        foreach (self::SUPPORTED_CURRENCIES as $currency) {
            if ($currency === $source) {
                $snapshot['rates'][$currency] = [
                    'amount' => $baseAmount,
                    'rate' => 1.0,
                ];

                continue;
            }

            $rate = $this->getExchangeRate($source, $currency);

            if ($rate === null) {
                $snapshot['rates'][$currency] = [
                    'amount' => null,
                    'rate' => null,
                ];

                continue;
            }

            $snapshot['rates'][$currency] = [
                'amount' => round($baseAmount * $rate, 2),
                'rate' => $rate,
            ];
        }

        return $snapshot;
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    protected function getExchangeRate(string $fromCurrency, string $toCurrency): ?float
    {
        $cacheKey = sprintf('exchange_rate_%s_%s', $fromCurrency, $toCurrency);
        $ttl = (int) config('services.currency_api.cache_ttl', 60 * 60 * 24 * 7); // weekly cache

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $rate = $this->fetchExchangeRate($fromCurrency, $toCurrency);

        if ($rate !== null) {
            Cache::put($cacheKey, $rate, $ttl);
        }

        return $rate;
    }

    protected function fetchExchangeRate(string $fromCurrency, string $toCurrency): ?float
    {
        $baseUrl = rtrim(config('services.currency_api.base_url', 'https://cdn.jsdelivr.net/gh/fawazahmed0/exchange-api@latest/v1'), '/');
        $endpoint = sprintf('%s/currencies/%s/%s.json', $baseUrl, strtolower($fromCurrency), strtolower($toCurrency));

        try {
            $response = Http::timeout(10)->acceptJson()->get($endpoint);

            if (!$response->successful()) {
                Log::warning('Failed to fetch exchange rate', [
                    'from' => $fromCurrency,
                    'to' => $toCurrency,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $data = $response->json();
            $rate = Arr::get($data, strtolower($toCurrency));

            if (is_numeric($rate)) {
                return (float) $rate;
            }
        } catch (\Throwable $exception) {
            Log::warning('Exchange rate request failed', [
                'from' => $fromCurrency,
                'to' => $toCurrency,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    protected function normalizeCurrency(?string $currency): string
    {
        $currency = strtoupper($currency ?? $this->defaultCurrency);

        if (!in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            return $this->defaultCurrency;
        }

        return $currency;
    }
}

<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CurrencyService
{
    public function getDefaultCurrency(): string
    {
        return Str::upper(config('currency.default', 'IDR'));
    }

    public function getBaseCurrency(): string
    {
        return Str::upper(config('currency.base', 'IDR'));
    }

    public function getSupportedCurrencies(): array
    {
        return config('currency.supported', []);
    }

    public function convert(float $amount, string $fromCurrency, ?string $toCurrency = null): float
    {
        $from = Str::upper($fromCurrency);
        $to = Str::upper($toCurrency ?? $this->getDefaultCurrency());

        if ($from === $to) {
            return $this->roundAmount($amount, $to);
        }

        try {
            $rate = $this->resolveExchangeRate($from, $to);
        } catch (InvalidArgumentException $exception) {
            Log::warning('Currency conversion failed.', [
                'from' => $from,
                'to' => $to,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $this->roundAmount($amount * $rate, $to);
    }

    public function getAmountInDefaultCurrency(float $amount, string $currency): array
    {
        $defaultCurrency = $this->getDefaultCurrency();

        try {
            return [
                'amount' => $this->convert($amount, $currency, $defaultCurrency),
                'currency' => $defaultCurrency,
            ];
        } catch (InvalidArgumentException $exception) {
            Log::warning('Falling back to original currency for amount conversion.', [
                'from' => $currency,
                'to' => $defaultCurrency,
                'message' => $exception->getMessage(),
            ]);

            return [
                'amount' => $this->roundAmount($amount, $currency),
                'currency' => Str::upper($currency),
            ];
        }
    }

    public function getExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $from = Str::upper($fromCurrency);
        $to = Str::upper($toCurrency);

        if ($from === $to) {
            return 1.0;
        }

        return $this->resolveExchangeRate($from, $to);
    }

    protected function resolveExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        $cacheKey = sprintf('currency_rate_%s_%s', $fromCurrency, $toCurrency);

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($fromCurrency, $toCurrency) {
            if ($rate = $this->findRate($fromCurrency, $toCurrency)) {
                return (float) $rate;
            }

            if ($inverseRate = $this->findRate($toCurrency, $fromCurrency)) {
                if ((float) $inverseRate == 0.0) {
                    throw new InvalidArgumentException('Invalid exchange rate value.');
                }

                return 1 / (float) $inverseRate;
            }

            $baseCurrency = $this->getBaseCurrency();

            if ($fromCurrency !== $baseCurrency) {
                $fromToBase = $this->resolveExchangeRate($fromCurrency, $baseCurrency);
                $baseToTarget = $this->resolveExchangeRate($baseCurrency, $toCurrency);

                return $fromToBase * $baseToTarget;
            }

            throw new InvalidArgumentException("Exchange rate from {$fromCurrency} to {$toCurrency} is not available.");
        });
    }

    protected function findRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $rate = CurrencyRate::query()
            ->where('base_currency', $baseCurrency)
            ->where('target_currency', $targetCurrency)
            ->orderByDesc('fetched_at')
            ->orderByDesc('updated_at')
            ->value('rate');

        return $rate !== null ? (float) $rate : null;
    }

    protected function roundAmount(float $amount, string $currency): float
    {
        $precision = $this->getCurrencyPrecision($currency);

        return round($amount, $precision);
    }

    protected function getCurrencyPrecision(string $currency): int
    {
        $currency = Str::upper($currency);
        $precisionConfig = config('currency.precision', []);

        return (int) ($precisionConfig[$currency] ?? ($currency === 'IDR' ? 0 : 2));
    }
}

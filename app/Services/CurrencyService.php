<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CurrencyService
{
    protected string $defaultCurrency;

    /** @var array<int, string> */
    protected array $supportedCurrencies;

    public function __construct(?string $defaultCurrency = null, ?array $supportedCurrencies = null)
    {
        $configuredDefault = $defaultCurrency ?? config('currency.default', 'IDR');
        $configuredSupported = $supportedCurrencies ?? config('currency.supported', ['IDR', 'USD']);

        $this->defaultCurrency = strtoupper($configuredDefault);
        $this->supportedCurrencies = array_values(array_unique(array_map('strtoupper', $configuredSupported)));
    }

    public function getDefaultCurrency(): string
    {
        return $this->defaultCurrency;
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedCurrencies(): array
    {
        return $this->supportedCurrencies;
    }

    public function isSupported(string $currency): bool
    {
        return in_array(strtoupper($currency), $this->supportedCurrencies, true);
    }

    public function convert(float|int|string $amount, string $fromCurrency, string $toCurrency, int $precision = 2): float
    {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if (!$this->isSupported($from) || !$this->isSupported($to)) {
            throw new InvalidArgumentException("Unsupported currency conversion from {$from} to {$to}");
        }

        $numericAmount = (float) $amount;

        if ($from === $to) {
            return round($numericAmount, $precision);
        }

        $baseCurrency = $this->defaultCurrency;
        $amountInBase = $numericAmount;

        if ($from !== $baseCurrency) {
            $rateToBase = $this->getRate($baseCurrency, $from);

            if (!$rateToBase || abs($rateToBase) < PHP_FLOAT_EPSILON) {
                throw new InvalidArgumentException("Unable to determine exchange rate for {$from} to {$baseCurrency}");
            }

            $amountInBase = $numericAmount / $rateToBase;
        }

        if ($to === $baseCurrency) {
            return round($amountInBase, $precision);
        }

        $rateFromBase = $this->getRate($baseCurrency, $to);

        if (!$rateFromBase || abs($rateFromBase) < PHP_FLOAT_EPSILON) {
            throw new InvalidArgumentException("Unable to determine exchange rate for {$baseCurrency} to {$to}");
        }

        return round($amountInBase * $rateFromBase, $precision);
    }

    public function getRate(string $baseCurrency, string $targetCurrency, bool $useFallback = true): ?float
    {
        $base = strtoupper($baseCurrency);
        $target = strtoupper($targetCurrency);

        if ($base === $target) {
            return 1.0;
        }

        $cacheKey = sprintf('%s.%s.%s', config('currency.cache_key', 'currency_rates'), $base, $target);

        return Cache::remember($cacheKey, config('currency.cache_ttl', 3600), function () use ($base, $target, $useFallback) {
            $rate = ExchangeRate::query()
                ->where('base_currency', $base)
                ->where('target_currency', $target)
                ->orderByDesc('retrieved_at')
                ->orderByDesc('updated_at')
                ->first();

            if ($rate) {
                return (float) $rate->rate;
            }

            if ($useFallback) {
                $fallback = $this->getFallbackRate($base, $target);

                if ($fallback !== null) {
                    Log::notice(sprintf('Using fallback exchange rate %s -> %s', $base, $target));
                }

                return $fallback;
            }

            return null;
        });
    }

    public function clearCachedRate(string $baseCurrency, string $targetCurrency): void
    {
        $base = strtoupper($baseCurrency);
        $target = strtoupper($targetCurrency);
        Cache::forget(sprintf('%s.%s.%s', config('currency.cache_key', 'currency_rates'), $base, $target));
    }

    public function getFallbackRate(string $baseCurrency, string $targetCurrency): ?float
    {
        $base = strtoupper($baseCurrency);
        $target = strtoupper($targetCurrency);

        if ($base === $target) {
            return 1.0;
        }

        $fallbackRates = config('currency.fallback_rates', []);
        $default = $this->defaultCurrency;

        if ($base === $default && isset($fallbackRates[$target])) {
            return (float) $fallbackRates[$target];
        }

        if ($target === $default && isset($fallbackRates[$base])) {
            $rate = (float) $fallbackRates[$base];
            if (abs($rate) < PHP_FLOAT_EPSILON) {
                return null;
            }

            return (float) (1 / $rate);
        }

        return null;
    }
}

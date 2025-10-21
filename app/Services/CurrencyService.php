<?php

namespace App\Services;

use App\Models\ExchangeRate;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CurrencyService
{
    /**
     * Cached exchange rates for the current request lifecycle.
     *
     * @var array<string, float>
     */
    protected array $rateCache = [];

    /**
     * Get the default currency code.
     */
    public function defaultCurrency(): string
    {
        return strtoupper(config('currency.default', 'IDR'));
    }

    /**
     * Get the base currency used for exchange calculations.
     */
    public function baseCurrency(): string
    {
        return strtoupper(config('currency.base', $this->defaultCurrency()));
    }

    /**
     * Get supported currency codes.
     *
     * @return array<int, string>
     */
    public function supportedCurrencies(): array
    {
        $supported = config('currency.supported', [$this->defaultCurrency()]);

        return array_values(array_unique(array_map('strtoupper', $supported)));
    }

    /**
     * Get human readable labels for supported currencies.
     *
     * @return array<string, string>
     */
    public function labels(): array
    {
        $labels = config('currency.labels', []);

        return collect($this->supportedCurrencies())
            ->mapWithKeys(fn ($currency) => [$currency => $labels[$currency] ?? $currency])
            ->all();
    }

    /**
     * Get human readable label for a specific currency.
     */
    public function label(string $currency): string
    {
        return $this->labels()[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * Convert amount from one currency to another using the latest rate.
     */
    public function convert(float $amount, string $from, string $to, int $precision = 2): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return round($amount, $precision);
        }

        $base = $this->baseCurrency();

        if ($from === $base) {
            $rate = $this->getRate($base, $to);

            return round($amount * $rate, $precision);
        }

        if ($to === $base) {
            $rate = $this->getRate($base, $from);

            if ($rate <= 0) {
                throw new RuntimeException("Exchange rate not available for {$from}/{$to}");
            }

            return round($amount / $rate, $precision);
        }

        $amountInBase = $this->convert($amount, $from, $base, 8);

        return $this->convert($amountInBase, $base, $to, $precision);
    }

    /**
     * Retrieve the latest exchange rate between two currencies.
     */
    public function getRate(string $base, string $target): float
    {
        $base = strtoupper($base);
        $target = strtoupper($target);

        if ($base === $target) {
            return 1.0;
        }

        $cacheKey = $base . '_' . $target;

        if (array_key_exists($cacheKey, $this->rateCache)) {
            return $this->rateCache[$cacheKey];
        }

        $rateModel = ExchangeRate::where('base_currency', $base)
            ->where('target_currency', $target)
            ->first();

        if ($rateModel && $rateModel->fetched_at && $rateModel->fetched_at->greaterThan(now()->subWeek())) {
            return $this->rateCache[$cacheKey] = (float) $rateModel->rate;
        }

        $freshRate = $this->fetchRateFromProvider($base, $target);

        if ($freshRate !== null) {
            if (! $rateModel) {
                $rateModel = new ExchangeRate([
                    'base_currency' => $base,
                    'target_currency' => $target,
                ]);
            }

            $rateModel->rate = $freshRate;
            $rateModel->fetched_at = now();
            $rateModel->save();

            return $this->rateCache[$cacheKey] = $freshRate;
        }

        if ($rateModel) {
            return $this->rateCache[$cacheKey] = (float) $rateModel->rate;
        }

        $fallbackRate = $this->fallbackRate($base, $target);

        if ($fallbackRate !== null) {
            ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $base,
                    'target_currency' => $target,
                ],
                [
                    'rate' => $fallbackRate,
                    'fetched_at' => now()->subWeek(),
                ]
            );

            return $this->rateCache[$cacheKey] = $fallbackRate;
        }

        throw new RuntimeException("Unable to determine exchange rate for {$base}/{$target}");
    }

    /**
     * Fetch the exchange rate from the configured provider.
     */
    protected function fetchRateFromProvider(string $base, string $target): ?float
    {
        $provider = config('currency.provider', []);
        $url = $provider['url'] ?? null;

        if (! $url) {
            return null;
        }

        try {
            $response = Http::timeout($provider['timeout'] ?? 10)->get($url, [
                'base' => $base,
                'symbols' => $target,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $rate = Arr::get($data, "rates.{$target}");

            if ($rate && is_numeric($rate) && (float) $rate > 0) {
                return (float) $rate;
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch exchange rate', [
                'base' => $base,
                'target' => $target,
                'message' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Determine a fallback rate when provider data is unavailable.
     */
    protected function fallbackRate(string $base, string $target): ?float
    {
        $fallbacks = config('currency.fallback_rates', []);

        if (isset($fallbacks[$base][$target])) {
            return (float) $fallbacks[$base][$target];
        }

        if (isset($fallbacks[$target][$base])) {
            $inverse = (float) $fallbacks[$target][$base];

            if ($inverse > 0) {
                return 1 / $inverse;
            }
        }

        return null;
    }
}

<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyRateService
{
    public function __construct(protected CurrencyRate $currencyRate)
    {
    }

    public function getRate(string $baseCurrency, string $targetCurrency): float
    {
        $base = strtoupper($baseCurrency);
        $target = strtoupper($targetCurrency);

        if ($base === $target) {
            return 1.0;
        }

        $record = $this->currencyRate->newQuery()
            ->where('base_currency', $base)
            ->where('target_currency', $target)
            ->first();

        $shouldRefresh = true;
        $refreshDays = max(1, (int) Config::get('currency.exchange_api.weekly_refresh_days', 7));

        if ($record && $record->fetched_at instanceof \DateTimeInterface) {
            $shouldRefresh = CarbonImmutable::now()->subDays($refreshDays)->greaterThan($record->fetched_at);
        }

        if (!$record) {
            $shouldRefresh = true;
        }

        if ($shouldRefresh) {
            $freshRate = $this->fetchRate($base, $target);

            if ($freshRate !== null) {
                $record = $this->currencyRate->newQuery()->updateOrCreate(
                    [
                        'base_currency' => $base,
                        'target_currency' => $target,
                    ],
                    [
                        'rate' => $freshRate,
                        'fetched_at' => CarbonImmutable::now(),
                        'provider' => 'fawazahmed0/exchange-api',
                    ]
                );

                if ($freshRate > 0) {
                    $inverse = 1 / $freshRate;
                    $this->currencyRate->newQuery()->updateOrCreate(
                        [
                            'base_currency' => $target,
                            'target_currency' => $base,
                        ],
                        [
                            'rate' => $inverse,
                            'fetched_at' => CarbonImmutable::now(),
                            'provider' => 'fawazahmed0/exchange-api',
                        ]
                    );
                }
            }
        }

        if (!$record) {
            Log::warning('Currency rate unavailable, falling back to neutral rate.', [
                'base' => $base,
                'target' => $target,
            ]);

            return 1.0;
        }

        return (float) $record->rate;
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency, int $precision = 2): float
    {
        $rate = $this->getRate($fromCurrency, $toCurrency);

        return round($amount * $rate, $precision);
    }

    public function normalizeAmounts(float $amount, string $currency): array
    {
        $base = strtoupper($currency ?: Config::get('currency.default', 'IDR'));

        if (!in_array($base, Config::get('currency.supported', []), true)) {
            $base = Config::get('currency.default', 'IDR');
        }

        if ($base === 'IDR') {
            $idr = round($amount, 2);
            $usd = $this->convert($amount, 'IDR', 'USD');
        } elseif ($base === 'USD') {
            $usd = round($amount, 2);
            $idr = $this->convert($amount, 'USD', 'IDR');
        } else {
            $idr = round($amount, 2);
            $usd = $this->convert($amount, 'IDR', 'USD');
        }

        return [
            'IDR' => round($idr, 2),
            'USD' => round($usd, 2),
        ];
    }

    public function getIdrUsdPair(): array
    {
        $idrToUsd = $this->getRate('IDR', 'USD');
        $usdToIdr = $this->getRate('USD', 'IDR');

        return [
            'idr_to_usd' => $idrToUsd,
            'usd_to_idr' => $usdToIdr,
        ];
    }

    protected function fetchRate(string $baseCurrency, string $targetCurrency): ?float
    {
        try {
            $baseUrl = rtrim(Config::get('currency.exchange_api.base_url'), '/');
            $path = sprintf('%s/%s/%s.json', $baseUrl, strtolower($baseCurrency), strtolower($targetCurrency));

            $response = Http::timeout((int) Config::get('currency.exchange_api.timeout', 10))
                ->acceptJson()
                ->get($path);

            if ($response->successful()) {
                $data = $response->json();
                $key = strtolower($targetCurrency);

                if (isset($data[$key])) {
                    return (float) $data[$key];
                }

                if (isset($data['rate'])) {
                    return (float) $data['rate'];
                }
            } else {
                Log::warning('Failed to fetch currency rate.', [
                    'base' => $baseCurrency,
                    'target' => $targetCurrency,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $exception) {
            Log::error('Currency rate fetch error: ' . $exception->getMessage(), [
                'base' => $baseCurrency,
                'target' => $targetCurrency,
            ]);
        }

        return null;
    }
}

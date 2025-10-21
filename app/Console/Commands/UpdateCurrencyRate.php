<?php

namespace App\Console\Commands;

use App\Models\CurrencyRate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class UpdateCurrencyRate extends Command
{
    protected $signature = 'currency:update-rate {base?} {target?}';

    protected $description = 'Fetch and store the latest exchange rate for the configured currencies.';

    public function handle(): int
    {
        $baseCurrency = Str::upper($this->argument('base') ?? config('currency.base', 'IDR'));
        $targetCurrency = Str::upper($this->argument('target') ?? 'USD');
        $endpoint = config('currency.update_source', 'https://api.exchangerate.host/latest');

        $this->info(sprintf('Updating currency rate from %s to %s...', $baseCurrency, $targetCurrency));

        try {
            $response = Http::timeout(10)->retry(3, 500)->get($endpoint, [
                'base' => $baseCurrency,
                'symbols' => $targetCurrency,
            ]);
        } catch (\Throwable $exception) {
            $this->error('Failed to fetch exchange rates: ' . $exception->getMessage());
            return self::FAILURE;
        }

        if (!$response->successful()) {
            $this->error('Exchange rate API responded with an error: ' . $response->status());
            return self::FAILURE;
        }

        $rate = data_get($response->json(), "rates.{$targetCurrency}");

        if (!is_numeric($rate)) {
            $this->error('The exchange rate response did not include a valid rate.');
            return self::FAILURE;
        }

        CurrencyRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'target_currency' => $targetCurrency,
            ],
            [
                'rate' => $rate,
                'fetched_at' => now(),
            ]
        );

        Cache::forget(sprintf('currency_rate_%s_%s', $baseCurrency, $targetCurrency));
        Cache::forget(sprintf('currency_rate_%s_%s', $targetCurrency, $baseCurrency));

        $this->info(sprintf('Exchange rate updated: 1 %s = %s %s', $baseCurrency, $rate, $targetCurrency));

        return self::SUCCESS;
    }
}

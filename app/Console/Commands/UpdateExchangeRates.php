<?php

namespace App\Console\Commands;

use App\Models\ExchangeRate;
use App\Services\CurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'currency:update {--force-fallback : Use fallback exchange rates without calling the API}';

    /**
     * The console command description.
     */
    protected $description = 'Update and cache the exchange rates for supported currencies.';

    public function handle(CurrencyService $currencyService): int
    {
        $baseCurrency = strtoupper(config('currency.api.base', $currencyService->getDefaultCurrency()));
        $symbols = config('currency.api.symbols', 'USD');
        $targetCurrencies = array_values(array_filter(array_map(static fn ($currency) => strtoupper(trim($currency)), explode(',', $symbols))));

        if (empty($targetCurrencies)) {
            $this->warn('No target currencies configured for exchange rate updates.');
            return self::INVALID;
        }

        if ($this->option('force-fallback')) {
            $this->applyFallbackRates($currencyService, $baseCurrency, $targetCurrencies);
            $this->info('Fallback exchange rates applied successfully.');

            return self::SUCCESS;
        }

        $endpoint = config('currency.api.endpoint');

        try {
            $response = Http::timeout(config('currency.api.timeout', 10))
                ->retry(3, 200)
                ->get($endpoint, [
                    'base' => $baseCurrency,
                    'symbols' => implode(',', $targetCurrencies),
                ]);

            if (!$response->successful()) {
                throw new \RuntimeException(sprintf('Exchange rate request failed with status %s', $response->status()));
            }

            $payload = $response->json();
            $rates = $payload['rates'] ?? [];

            if (!is_array($rates) || empty($rates)) {
                throw new \RuntimeException('Unexpected exchange rate payload received from provider.');
            }

            foreach ($targetCurrencies as $targetCurrency) {
                if (!isset($rates[$targetCurrency])) {
                    $this->warn(sprintf('Exchange rate for %s was not included in the provider response.', $targetCurrency));
                    continue;
                }

                $rate = (float) $rates[$targetCurrency];

                ExchangeRate::updateOrCreate(
                    [
                        'base_currency' => $baseCurrency,
                        'target_currency' => $targetCurrency,
                    ],
                    [
                        'rate' => $rate,
                        'provider' => parse_url($endpoint, PHP_URL_HOST) ?: 'unknown-provider',
                        'retrieved_at' => now(),
                    ]
                );

                $currencyService->clearCachedRate($baseCurrency, $targetCurrency);
                $currencyService->clearCachedRate($targetCurrency, $baseCurrency);

                $this->line(sprintf('Updated %s -> %s rate: %0.8f', $baseCurrency, $targetCurrency, $rate));
            }

            $this->info('Exchange rates updated successfully.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            Log::error('Failed to update exchange rates', [
                'message' => $exception->getMessage(),
            ]);

            $this->error('Failed to update exchange rates from remote provider: ' . $exception->getMessage());
            $this->warn('Applying fallback rates instead.');

            $this->applyFallbackRates($currencyService, $baseCurrency, $targetCurrencies);

            return self::FAILURE;
        }
    }

    /**
     * Apply fallback rates when remote updates fail or are skipped.
     *
     * @param array<int, string> $targetCurrencies
     */
    protected function applyFallbackRates(CurrencyService $currencyService, string $baseCurrency, array $targetCurrencies): void
    {
        foreach ($targetCurrencies as $targetCurrency) {
            $fallbackRate = $currencyService->getFallbackRate($baseCurrency, $targetCurrency);

            if ($fallbackRate === null) {
                $this->warn(sprintf('No fallback rate configured for %s -> %s.', $baseCurrency, $targetCurrency));
                continue;
            }

            ExchangeRate::updateOrCreate(
                [
                    'base_currency' => $baseCurrency,
                    'target_currency' => $targetCurrency,
                ],
                [
                    'rate' => $fallbackRate,
                    'provider' => 'fallback',
                    'retrieved_at' => now(),
                ]
            );

            $currencyService->clearCachedRate($baseCurrency, $targetCurrency);
            $currencyService->clearCachedRate($targetCurrency, $baseCurrency);

            $this->line(sprintf('Applied fallback rate %s -> %s: %0.8f', $baseCurrency, $targetCurrency, $fallbackRate));
        }
    }
}

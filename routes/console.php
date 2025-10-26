<?php

use App\Services\ExchangeRateService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exchange:refresh', function (ExchangeRateService $exchangeRates) {
    $currencies = collect(ExchangeRateService::SUPPORTED_CURRENCIES)
        ->map(fn (string $currency) => strtoupper($currency))
        ->unique()
        ->values();

    if ($currencies->count() < 2) {
        $this->warn('At least two currencies are required to refresh exchange rates.');

        return 0;
    }

    $updatedPairs = 0;
    $failed = false;

    for ($i = 0; $i < $currencies->count(); $i++) {
        for ($j = $i + 1; $j < $currencies->count(); $j++) {
            $base = $currencies[$i];
            $target = $currencies[$j];

            try {
                $rate = $exchangeRates->refreshRate($base, $target);
                $this->info(sprintf('Updated %s -> %s rate: %s', $base, $target, $rate));
                $updatedPairs++;
            } catch (\Throwable $exception) {
                Log::error('Failed to refresh exchange rate pair', [
                    'base' => $base,
                    'target' => $target,
                    'exception' => $exception->getMessage(),
                ]);

                $this->error(sprintf('Failed to update %s -> %s: %s', $base, $target, $exception->getMessage()));
                $failed = true;
            }
        }
    }

    $this->comment(sprintf('Finished refreshing %d rate pair(s).', $updatedPairs));

    return $failed ? 1 : 0;
})->purpose('Refresh and cache exchange rates for supported currencies.');

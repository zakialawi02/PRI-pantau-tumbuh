<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Command;

class UpdateExchangeRates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'currency:update {--force : Force a refresh from the exchange rate API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronise configured exchange rates with the latest values from the currency API.';

    /**
     * Execute the console command.
     */
    public function handle(CurrencyService $currencyService): int
    {
        $force = (bool) $this->option('force');

        $rate = $currencyService->refreshRates($force);

        $this->info(sprintf('IDR to USD exchange rate updated to %s', number_format($rate, 8)));

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Services\ExchangeRateService;
use Illuminate\Console\Command;

class UpdateExchangeRates extends Command
{
    protected $signature = 'exchange:update';

    protected $description = 'Refresh cached USD/IDR exchange rates.';

    public function __construct(private readonly ExchangeRateService $exchangeRateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $pairs = [
            ['USD', 'IDR'],
            ['IDR', 'USD'],
        ];

        foreach ($pairs as [$base, $target]) {
            $rate = $this->exchangeRateService->refreshRate($base, $target);
            if ($rate !== null) {
                $this->info(sprintf('Updated %s -> %s rate: %s', $base, $target, $rate));
            } else {
                $this->warn(sprintf('Failed to update %s -> %s rate', $base, $target));
            }
        }

        $this->info('Exchange rate refresh completed.');

        return self::SUCCESS;
    }
}

<?php

namespace Database\Seeders;

use App\Models\CurrencyRate;
use Illuminate\Database\Seeder;

class CurrencyRateSeeder extends Seeder
{
    public function run(): void
    {
        $baseCurrency = config('currency.base', 'IDR');
        $defaultTarget = 'USD';

        CurrencyRate::updateOrCreate(
            [
                'base_currency' => $baseCurrency,
                'target_currency' => $defaultTarget,
            ],
            [
                'rate' => 0.000065,
                'fetched_at' => now(),
            ]
        );
    }
}

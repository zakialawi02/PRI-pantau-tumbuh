<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Services\CurrencyService;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var CurrencyService $currencyService */
        $currencyService = app(CurrencyService::class);

        $defaultCurrency = $currencyService->defaultCurrency();

        $plans = [
            [
                'name' => 'Starter Pack',
                'credit_points' => 25,
                'price' => 3,
                'currency' => 'USD',
                'isShow' => true,
                'isFeatured' => false,
            ],
            [
                'name' => 'Standard',
                'credit_points' => 50,
                'price' => 5,
                'currency' => 'USD',
                'isShow' => true,
                'isFeatured' => false,
            ],
            [
                'name' => 'Professional',
                'credit_points' => 100,
                'price' => 9,
                'currency' => 'USD',
                'isShow' => true,
                'isFeatured' => true,
            ],
            [
                'name' => 'Enterprise',
                'credit_points' => 200,
                'price' => 18,
                'currency' => 'USD',
                'isShow' => true,
                'isFeatured' => false,
            ],
        ];

        foreach ($plans as $planData) {
            $priceInDefault = $currencyService->convert(
                (float) $planData['price'],
                $planData['currency'],
                $defaultCurrency,
                2
            );

            Plan::updateOrCreate(
                ['name' => $planData['name']],
                [
                    'credit_points' => $planData['credit_points'],
                    'price' => $priceInDefault,
                    'currency' => $defaultCurrency,
                    'isShow' => $planData['isShow'],
                    'isFeatured' => $planData['isFeatured'],
                ]
            );
        }
    }
}

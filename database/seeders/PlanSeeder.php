<?php

namespace Database\Seeders;

use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $plans = [
            [
                'id' => (string) Str::uuid(),
                'name' => 'Starter Pack',
                'credit_points' => 25,
                'price' => 45000,
                'currency' => 'IDR',
                'price_idr' => 45000,
                'price_usd' => 3,
                'base_currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Standard',
                'credit_points' => 50,
                'price' => 75000,
                'currency' => 'IDR',
                'price_idr' => 75000,
                'price_usd' => 5,
                'base_currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Professional',
                'credit_points' => 100,
                'price' => 135000,
                'currency' => 'IDR',
                'price_idr' => 135000,
                'price_usd' => 9,
                'base_currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Enterprise',
                'credit_points' => 200,
                'price' => 270000,
                'currency' => 'IDR',
                'price_idr' => 270000,
                'price_usd' => 18,
                'base_currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('plans')->insert($plans);
    }
}

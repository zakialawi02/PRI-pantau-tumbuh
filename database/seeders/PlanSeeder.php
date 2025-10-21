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
                'price' => 48000,
                'currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Standard',
                'credit_points' => 50,
                'price' => 80000,
                'currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Professional',
                'credit_points' => 100,
                'price' => 144000,
                'currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Enterprise',
                'credit_points' => 200,
                'price' => 288000,
                'currency' => 'IDR',
                'isShow' => true,
                'isFeatured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('plans')->insert($plans);
    }
}

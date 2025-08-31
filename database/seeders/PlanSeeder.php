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
        DB::table('plans')->insert([
            'id' => (string) Str::uuid(),
            'name' => 'Standard',
            'price_per_hectare' => 1.5,
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

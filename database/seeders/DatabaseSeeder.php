<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(CurrencyRateSeeder::class);
        $this->call(UsersSeeder::class);
        // User::factory(200)->create();
        $this->call(PlanSeeder::class);
        $this->call(UserCreditsSeeder::class);
    }
}

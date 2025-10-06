<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserCreditsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing users
        $users = DB::table('users')->select('id')->get();

        // Prepare data for insertion
        $creditRecords = [];
        $defaultCredits = 0;
        $timestamp = now();

        foreach ($users as $user) {
            // Check if the user already has a credit record
            $existingCredit = DB::table('user_credits')
                ->where('user_id', $user->id)
                ->first();

            // If no credit record exists, prepare one for insertion
            if (!$existingCredit) {
                $creditRecords[] = [
                    'user_id' => $user->id,
                    'credits' => $defaultCredits,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        // Insert all credit records at once for better performance
        if (!empty($creditRecords)) {
            DB::table('user_credits')->insert($creditRecords);
        }
    }
}

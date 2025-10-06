<?php

use App\Models\User;
use App\Models\UserCredit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the user_credits table
        Schema::create('user_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->integer('credits')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });

        // Populate existing users with default credit records
        $this->populateUserCredits();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_credits');
    }

    /**
     * Populate existing users with default credit records.
     */
    private function populateUserCredits(): void
    {
        // Get all existing users using the User model
        $users = User::select('id')->withTrashed()->get();

        // Prepare data for insertion
        $creditRecords = [];
        $defaultCredits = 0;
        $timestamp = now();

        foreach ($users as $user) {
            // Check if the user already has a credit record using the UserCredit model
            $existingCredit = UserCredit::where('user_id', $user->id)->first();

            // If no credit record exists, prepare one for insertion
            if (!$existingCredit) {
                $creditRecords[] = [
                    'id' => \Illuminate\Support\Str::ulid(),
                    'user_id' => $user->id,
                    'credits' => $defaultCredits,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        // Insert all credit records at once for better performance
        if (!empty($creditRecords)) {
            UserCredit::insert($creditRecords);
        }
    }
};

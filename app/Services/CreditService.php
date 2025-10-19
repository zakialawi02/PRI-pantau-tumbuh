<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{

    /**
     * Refund credits to user when imagery processing fails
     *
     * @param mixed $imagery The imagery object that failed processing
     * @param float|null $creditCost The amount of credits to refund (null to use default)
     * @param string $logContext Context for logging
     * @return bool True if refund was successful, false otherwise
     */
    public function refundCreditsForFailure($imagery, ?float $creditCost = null, string $logContext = 'System'): bool
    {
        try {
            // Use default credit cost if not provided
            if ($creditCost === null) {
                $creditCost = config('app-constants.imagery_processing_cost', 10);
            }

            // Get the user who owns this imagery
            $user = $imagery->user;
            if (!$user) {
                Log::error("❌ [{$logContext}] User not found for imagery: {$imagery->id}");
                return false;
            }

            // Get user's credit record
            $userCredit = $user->credits;
            if (!$userCredit) {
                Log::error("❌ [{$logContext}] User credit record not found for user: {$user->id}");
                return false;
            }

            // Refund the credits
            $userCredit->credits += $creditCost;
            $userCredit->save();

            Log::info("💰 [{$logContext}] Refunded {$creditCost} credits to user {$user->id} for failed imagery processing: {$imagery->id}");
            return true;
        } catch (\Exception $e) {
            Log::error("❌ [{$logContext}] Failed to refund credits for imagery {$imagery->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deduct credits from user for imagery processing
     *
     * @param string $user The user to deduct credits from
     * @param float $amount The amount of credits to deduct
     * @param string $logContext Context for logging
     * @return bool True if deduction was successful, false otherwise
     */
    public function deductCreditsForProcessing(String $user, float $amount = null, string $logContext = 'System'): bool
    {
        try {
            // Use a database transaction with lock for race condition handling
            $result = DB::transaction(function () use ($user, $amount, $logContext) {
                // Find user and lock the credits row for update
                $user = User::find($user);
                if (!$user) {
                    Log::error("❌ [{$logContext}] User not found: {$user}");
                    return false;
                }

                // Get credit cost from config if amount not provided
                if ($amount === null) {
                    $amount = config('app-constants.imagery_processing_cost', 10);
                }

                // Lock the user's credit record for update to prevent race conditions
                $userCredit = $user->credits()->lockForUpdate()->first();
                if (!$userCredit) {
                    Log::error("❌ [{$logContext}] User credit record not found for user: {$user->id}");
                    return false;
                }

                // Check if user has enough credits
                if ($userCredit->credits < $amount) {
                    Log::error("❌ [{$logContext}] Insufficient credits for user {$user->id}. Required: {$amount}, Available: {$userCredit->credits}");
                    return false;
                }

                // Deduct the credits
                $userCredit->credits -= $amount;
                $userCredit->save();

                Log::info("💰 [{$logContext}] Deducted {$amount} credits from user {$user->id}. Remaining: {$userCredit->credits}");
                return true;
            }, 3); // Retry up to 3 times in case of deadlock

            return $result;
        } catch (\Exception $e) {
            Log::error("❌ [{$logContext}] Failed to deduct credits for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the remaining credits for a user
     *
     * @param mixed $user The user to check credits for (can be User object or user ID)
     * @return float The remaining credits for the user
     */
    public function getRemainingCredits($user): float
    {
        $user = User::find($user);
        $userCredit = $user->credits;
        if (!$userCredit) {
            return 0;
        }

        return $userCredit->credits;
    }

    /**
     * Add credits to user
     *
     * @param string $user The user to add credits to
     * @param float $amount The amount of credits to add
     * @param string $logContext Context for logging
     * @return bool True if addition was successful, false otherwise
     */
    public function addCreditsToUser(String $user, float $amount, string $logContext = 'System'): bool
    {
        try {
            $user = User::find($user);
            // Use a database transaction with lock for race condition handling
            $result = DB::transaction(function () use ($user, $amount, $logContext) {
                // Lock the user's credit record for update to prevent race conditions
                $userCredit = $user->credits()->lockForUpdate()->first();
                if (!$userCredit) {
                    Log::error("❌ [{$logContext}] User credit record not found for user: {$user->id}");
                    return false;
                }

                // Add the credits
                $userCredit->credits += $amount;
                $userCredit->save();

                Log::info("💰 [{$logContext}] Added {$amount} credits to user {$user->id}. Total: {$userCredit->credits}");
                return true;
            }, 3); // Retry up to 3 times in case of deadlock

            return $result;
        } catch (\Exception $e) {
            Log::error("❌ [{$logContext}] Failed to add credits for user {$user->id}: " . $e->getMessage());
            return false;
        }
    }
}

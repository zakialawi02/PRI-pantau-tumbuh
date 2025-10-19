<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCreditHistory;
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
    public function refundCreditsForFailure($imagery, ?float $creditCost = null, string $logContext = 'System', ?string $description = null, ?array $meta = null): bool
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
            $previousCredits = (float) $userCredit->credits;
            $userCredit->credits += $creditCost;
            $userCredit->save();

            UserCreditHistory::record(
                (string) $user->id,
                'increase',
                $creditCost,
                $previousCredits,
                (float) $userCredit->credits,
                $description ?? "Refunded credits due to {$logContext}",
                array_merge(['imagery_id' => $imagery->id ?? null, 'context' => $logContext], $meta ?? [])
            );

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
     * @param \App\Models\User|string|int $user The user or user identifier to deduct credits from
     * @param float $amount The amount of credits to deduct
     * @param string $logContext Context for logging
     * @return bool True if deduction was successful, false otherwise
     */
    public function deductCreditsForProcessing($user, float $amount = null, string $logContext = 'System', ?string $description = null, ?array $meta = null): bool
    {
        try {
            $userId = $user instanceof User ? $user->getKey() : $user;
            if (empty($userId)) {
                Log::error("❌ [{$logContext}] Missing user identifier for credit deduction.");
                return false;
            }

            $deductionAmount = $amount ?? config('app-constants.imagery_processing_cost', 10);

            // Use a database transaction with lock for race condition handling
            $result = DB::transaction(function () use ($userId, $deductionAmount, $logContext, $description, $meta) {
                // Find user and lock the credits row for update
                $creditUser = User::find($userId);
                if (!$creditUser) {
                    Log::error("❌ [{$logContext}] User not found: {$userId}");
                    return false;
                }

                // Lock the user's credit record for update to prevent race conditions
                $userCredit = $creditUser->credits()->lockForUpdate()->first();
                if (!$userCredit) {
                    Log::error("❌ [{$logContext}] User credit record not found for user: {$creditUser->id}");
                    return false;
                }

                // Check if user has enough credits
                if ($userCredit->credits < $deductionAmount) {
                    Log::error("❌ [{$logContext}] Insufficient credits for user {$creditUser->id}. Required: {$deductionAmount}, Available: {$userCredit->credits}");
                    return false;
                }

                // Deduct the credits
                $previousCredits = (float) $userCredit->credits;
                $userCredit->credits -= $deductionAmount;
                $userCredit->save();

                UserCreditHistory::record(
                    (string) $creditUser->id,
                    'decrease',
                    $deductionAmount,
                    $previousCredits,
                    (float) $userCredit->credits,
                    $description ?? "Credits deducted for {$logContext}",
                    array_merge($meta ?? [], ['context' => $logContext])
                );

                Log::info("💰 [{$logContext}] Deducted {$deductionAmount} credits from user {$creditUser->id}. Remaining: {$userCredit->credits}");
                return true;
            }, 3); // Retry up to 3 times in case of deadlock

            return $result;
        } catch (\Throwable $e) {
            $userReference = $user instanceof User ? $user->getKey() : $user;
            Log::error("❌ [{$logContext}] Failed to deduct credits for user {$userReference}: " . $e->getMessage(), [
                'exception' => $e,
            ]);
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
    public function addCreditsToUser(String $user, float $amount, string $logContext = 'System', ?string $description = null, ?array $meta = null): bool
    {
        try {
            $user = User::find($user);
            // Use a database transaction with lock for race condition handling
            $result = DB::transaction(function () use ($user, $amount, $logContext, $description, $meta) {
                // Lock the user's credit record for update to prevent race conditions
                $userCredit = $user->credits()->lockForUpdate()->first();
                if (!$userCredit) {
                    Log::error("❌ [{$logContext}] User credit record not found for user: {$user->id}");
                    return false;
                }

                // Add the credits
                $previousCredits = (float) $userCredit->credits;
                $userCredit->credits += $amount;
                $userCredit->save();

                UserCreditHistory::record(
                    (string) $user->id,
                    'increase',
                    $amount,
                    $previousCredits,
                    (float) $userCredit->credits,
                    $description ?? "Credits added via {$logContext}",
                    array_merge($meta ?? [], ['context' => $logContext])
                );

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

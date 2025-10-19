<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCreditHistory;
use Illuminate\Support\Facades\Auth;
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
                $userCredit = $user->credits()->create([
                    'credits' => 0,
                ]);
            }

            $balanceBefore = (float) $userCredit->credits;

            // Refund the credits
            $userCredit->credits = $balanceBefore + $creditCost;
            $userCredit->save();

            $this->logHistory(
                $user,
                'credit',
                $creditCost,
                $balanceBefore,
                (float) $userCredit->credits,
                __('Credits refunded for failed imagery processing #:id', ['id' => $imagery->id]),
                [
                    'imagery_id' => $imagery->id,
                    'reason' => 'processing_failed',
                ],
                null,
                'imagery',
                (string) $imagery->id
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
     * @param string $userId The user to deduct credits from
     * @param float $amount The amount of credits to deduct
     * @param string $logContext Context for logging
     * @return bool True if deduction was successful, false otherwise
     */
    public function deductCreditsForProcessing(
        string $userId,
        float $amount = null,
        string $logContext = 'System',
        ?string $description = null,
        array $meta = [],
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $performedBy = null
    ): bool
    {
        try {
            // Use a database transaction with lock for race condition handling
            $result = DB::transaction(function () use ($userId, $amount, $logContext, $description, $meta, $referenceType, $referenceId, $performedBy) {
                // Find user and lock the credits row for update
                $user = User::find($userId);
                if (!$user) {
                    Log::error("❌ [{$logContext}] User not found: {$userId}");
                    return false;
                }

                // Get credit cost from config if amount not provided
                if ($amount === null) {
                    $amount = config('app-constants.imagery_processing_cost', 10);
                }

                // Lock the user's credit record for update to prevent race conditions
                $userCredit = $user->credits()->lockForUpdate()->first();
                if (!$userCredit) {
                    $userCredit = $user->credits()->create([
                        'credits' => 0,
                    ]);
                }

                $balanceBefore = (float) $userCredit->credits;

                // Check if user has enough credits
                if ($balanceBefore < $amount) {
                    Log::error("❌ [{$logContext}] Insufficient credits for user {$user->id}. Required: {$amount}, Available: {$balanceBefore}");
                    return false;
                }

                // Deduct the credits
                $userCredit->credits = $balanceBefore - $amount;
                $userCredit->save();

                $this->logHistory(
                    $user,
                    'debit',
                    $amount,
                    $balanceBefore,
                    (float) $userCredit->credits,
                    $description ?? __('Credits deducted for processing'),
                    $meta,
                    $performedBy,
                    $referenceType,
                    $referenceId
                );

                Log::info("💰 [{$logContext}] Deducted {$amount} credits from user {$user->id}. Remaining: {$userCredit->credits}");
                return true;
            }, 3); // Retry up to 3 times in case of deadlock

            return (bool) $result;
        } catch (\Throwable $e) {
            Log::error("❌ [{$logContext}] Failed to deduct credits for user {$userId}: " . $e->getMessage());
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
    public function addCreditsToUser(
        string $userId,
        float $amount,
        string $logContext = 'System',
        ?string $description = null,
        array $meta = [],
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $performedBy = null
    ): bool
    {
        try {
            $user = User::find($userId);
            if (!$user) {
                Log::error("❌ [{$logContext}] User not found: {$userId}");
                return false;
            }
            // Use a database transaction with lock for race condition handling
            $result = DB::transaction(function () use ($user, $amount, $logContext, $description, $meta, $referenceType, $referenceId, $performedBy) {
                // Lock the user's credit record for update to prevent race conditions
                $userCredit = $user->credits()->lockForUpdate()->first();
                if (!$userCredit) {
                    $userCredit = $user->credits()->create([
                        'credits' => 0,
                    ]);
                }

                $balanceBefore = (float) $userCredit->credits;

                // Add the credits
                $userCredit->credits = $balanceBefore + $amount;
                $userCredit->save();

                $this->logHistory(
                    $user,
                    'credit',
                    $amount,
                    $balanceBefore,
                    (float) $userCredit->credits,
                    $description ?? __('Credits added'),
                    $meta,
                    $performedBy,
                    $referenceType,
                    $referenceId
                );

                Log::info("💰 [{$logContext}] Added {$amount} credits to user {$user->id}. Total: {$userCredit->credits}");
                return true;
            }, 3); // Retry up to 3 times in case of deadlock

            return (bool) $result;
        } catch (\Throwable $e) {
            Log::error("❌ [{$logContext}] Failed to add credits for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    public function logHistory(
        User $user,
        string $type,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        ?string $description = null,
        array $meta = [],
        ?int $performedBy = null,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): void {
        if ($amount <= 0) {
            return;
        }

        UserCreditHistory::create([
            'user_id' => $user->id,
            'performed_by' => $performedBy ?? Auth::id(),
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'type' => $type,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'meta' => empty($meta) ? null : $meta,
        ]);
    }
}

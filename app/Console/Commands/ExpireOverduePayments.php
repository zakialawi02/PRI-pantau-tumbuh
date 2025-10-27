<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class ExpireOverduePayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:expire-overdue {--dry-run : Display how many payments would be marked as expired without persisting changes.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark overdue payments as expired in the database.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Payment::eligibleForExpiration();

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->info($count . ' payment(s) would be marked as expired.');

            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => 'expired',
            'updated_at' => now(),
        ]);

        if ($updated === 0) {
            $this->info('No overdue payments to mark as expired.');
        } else {
            $this->info('Marked ' . $updated . ' overdue payment(s) as expired.');
        }

        return self::SUCCESS;
    }
}

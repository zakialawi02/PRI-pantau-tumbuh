<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency');
            $table->decimal('amount_idr', 15, 2)->nullable()->after('exchange_rate');
            $table->decimal('amount_usd', 15, 2)->nullable()->after('amount_idr');
        });

        DB::table('payments')
            ->select(['id', 'amount', 'currency'])
            ->orderBy('id')
            ->chunkById(100, function ($payments) {
                foreach ($payments as $payment) {
                    $currency = strtoupper((string) $payment->currency);
                    $update = [];

                    if ($currency === 'IDR') {
                        $update['amount_idr'] = $payment->amount;
                    } elseif ($currency === 'USD') {
                        $update['amount_usd'] = $payment->amount;
                    }

                    if (!empty($update)) {
                        DB::table('payments')->where('id', $payment->id)->update($update);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'amount_idr', 'amount_usd']);
        });
    }
};

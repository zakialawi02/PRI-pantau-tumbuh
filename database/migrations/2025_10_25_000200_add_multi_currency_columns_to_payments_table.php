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
            $table->decimal('price_idr', 15, 2)->nullable()->after('exchange_rate');
            $table->decimal('price_usd', 15, 2)->nullable()->after('price_idr');
        });

        DB::table('payments')
            ->select(['id', 'price', 'currency'])
            ->orderBy('id')
            ->chunkById(100, function ($payments) {
                foreach ($payments as $payment) {
                    $currency = strtoupper((string) $payment->currency);
                    $update = [];

                    if ($currency === 'IDR') {
                        $update['price_idr'] = $payment->price;
                    } elseif ($currency === 'USD') {
                        $update['price_usd'] = $payment->price;
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
            $table->dropColumn(['exchange_rate', 'price_idr', 'price_usd']);
        });
    }
};

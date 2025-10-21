<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

        $fallbackRate = (float) config('currency.fallback_rates.USD', 0.000064);
        $usdToIdr = $fallbackRate > 0 ? 1 / $fallbackRate : null;

        if ($usdToIdr) {
            DB::table('plans')
                ->where('currency', 'USD')
                ->update([
                    'price' => DB::raw('ROUND(price * ' . $usdToIdr . ', 2)'),
                    'currency' => 'IDR',
                ]);

            DB::table('payments')
                ->where('currency', 'USD')
                ->update([
                    'amount_idr' => DB::raw('ROUND(amount * ' . $usdToIdr . ', 2)'),
                    'amount_usd' => DB::raw('ROUND(amount, 2)'),
                    'exchange_rate' => $fallbackRate,
                    'amount' => DB::raw('ROUND(amount * ' . $usdToIdr . ', 2)'),
                    'currency' => 'IDR',
                ]);
        }

        DB::statement("ALTER TABLE plans MODIFY currency VARCHAR(10) NOT NULL DEFAULT 'IDR'");
        DB::statement("ALTER TABLE payments MODIFY currency VARCHAR(10) NOT NULL DEFAULT 'IDR'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE plans MODIFY currency VARCHAR(10) NOT NULL DEFAULT 'USD'");
        DB::statement("ALTER TABLE payments MODIFY currency VARCHAR(10) NOT NULL DEFAULT 'USD'");

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['amount_usd', 'amount_idr', 'exchange_rate']);
        });
    }
};

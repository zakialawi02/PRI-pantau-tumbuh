<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'amount_idr')) {
                $table->decimal('amount_idr', 30, 2)->after('amount')->nullable();
            }

            if (!Schema::hasColumn('payments', 'amount_usd')) {
                $table->decimal('amount_usd', 30, 2)->after('amount_idr')->nullable();
            }

            if (!Schema::hasColumn('payments', 'exchange_rate_idr_to_usd')) {
                $table->decimal('exchange_rate_idr_to_usd', 20, 10)->after('amount_usd')->nullable();
            }

            if (!Schema::hasColumn('payments', 'exchange_rate_usd_to_idr')) {
                $table->decimal('exchange_rate_usd_to_idr', 20, 10)->after('exchange_rate_idr_to_usd')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'exchange_rate_usd_to_idr')) {
                $table->dropColumn('exchange_rate_usd_to_idr');
            }

            if (Schema::hasColumn('payments', 'exchange_rate_idr_to_usd')) {
                $table->dropColumn('exchange_rate_idr_to_usd');
            }

            if (Schema::hasColumn('payments', 'amount_usd')) {
                $table->dropColumn('amount_usd');
            }

            if (Schema::hasColumn('payments', 'amount_idr')) {
                $table->dropColumn('amount_idr');
            }
        });
    }
};

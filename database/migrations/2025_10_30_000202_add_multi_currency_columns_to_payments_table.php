<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('currency');
            $table->decimal('amount_idr', 15, 2)->nullable()->after('exchange_rate');
            $table->decimal('amount_usd', 15, 2)->nullable()->after('amount_idr');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'amount_idr', 'amount_usd']);
        });
    }
};

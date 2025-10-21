<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_idr', 18, 2)->nullable()->after('amount');
            $table->decimal('amount_usd', 18, 2)->nullable()->after('amount_idr');
            $table->decimal('exchange_rate', 18, 8)->nullable()->after('amount_usd');
            $table->timestamp('exchange_rate_updated_at')->nullable()->after('exchange_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'amount_idr',
                'amount_usd',
                'exchange_rate',
                'exchange_rate_updated_at',
            ]);
        });
    }
};

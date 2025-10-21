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
            $table->decimal('base_amount', 30, 2)->nullable()->after('amount');
            $table->string('base_currency', 10)->nullable()->after('base_amount');
            $table->decimal('exchange_rate', 30, 8)->nullable()->after('currency');
            $table->json('currency_details')->nullable()->after('exchange_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'base_amount',
                'base_currency',
                'exchange_rate',
                'currency_details',
            ]);
        });
    }
};

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
        if (Schema::hasColumn('payments', 'amount')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('amount', 'price');
            });
        }

        if (Schema::hasColumn('payments', 'amount_idr')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('amount_idr', 'price_idr');
            });
        }

        if (Schema::hasColumn('payments', 'amount_usd')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('amount_usd', 'price_usd');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('payments', 'price')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('price', 'amount');
            });
        }

        if (Schema::hasColumn('payments', 'price_idr')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('price_idr', 'amount_idr');
            });
        }

        if (Schema::hasColumn('payments', 'price_usd')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->renameColumn('price_usd', 'amount_usd');
            });
        }
    }
};

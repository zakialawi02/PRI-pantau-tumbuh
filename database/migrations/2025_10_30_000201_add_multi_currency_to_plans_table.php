<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_idr', 15, 2)->nullable()->after('credit_points');
            $table->decimal('price_usd', 15, 2)->nullable()->after('price_idr');
            $table->string('base_currency', 10)->default('IDR')->after('price_usd');
        });

        DB::table('plans')->select('id', 'price', 'currency')->orderBy('id')->chunk(100, function ($plans) {
            foreach ($plans as $plan) {
                $currency = strtoupper($plan->currency ?? 'IDR');
                $priceIdr = null;
                $priceUsd = null;

                if ($currency === 'USD') {
                    $priceUsd = $plan->price;
                } else {
                    $priceIdr = $plan->price;
                    $currency = 'IDR';
                }

                DB::table('plans')
                    ->where('id', $plan->id)
                    ->update([
                        'price_idr' => $priceIdr,
                        'price_usd' => $priceUsd,
                        'base_currency' => $currency,
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_idr', 'price_usd', 'base_currency']);
        });
    }
};

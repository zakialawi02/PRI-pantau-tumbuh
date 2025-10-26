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
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_idr', 15, 2)->nullable()->after('price');
            $table->decimal('price_usd', 15, 2)->nullable()->after('price_idr');
        });

        DB::table('plans')
            ->select(['id', 'price', 'currency'])
            ->orderBy('id')
            ->chunkById(100, function ($plans) {
                foreach ($plans as $plan) {
                    $currency = strtoupper((string) $plan->currency);
                    $update = [];

                    if ($currency === 'IDR') {
                        $update['price_idr'] = $plan->price;
                    } elseif ($currency === 'USD') {
                        $update['price_usd'] = $plan->price;
                    }

                    if (!empty($update)) {
                        DB::table('plans')->where('id', $plan->id)->update($update);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['price_idr', 'price_usd']);
        });
    }
};

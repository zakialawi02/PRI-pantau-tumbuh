<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency', 10);
            $table->string('target_currency', 10);
            $table->decimal('rate', 18, 8);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['base_currency', 'target_currency'], 'currency_rates_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};

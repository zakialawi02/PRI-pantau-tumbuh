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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('field_area_id');
            $table->uuid('plan_id');
            $table->decimal('price_per_hectare', 10, 2); // snapshot harga plan
            $table->decimal('total_price', 30, 2); // calculated (area * price_per_hectare)
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable(); // satu bulan ke depan
            $table->enum('status', ['active', 'expired', 'cancelled', 'trial', 'awaiting_payment', 'suspended'])->default('active');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('field_area_id')->references('id')->on('field_areas')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

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
        // First, drop the foreign key constraint from payments table, then drop the subscription_id column
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'subscription_id')) {
                $table->dropForeign(['subscription_id']);
                $table->dropColumn('subscription_id');
            }
        });

        // Then drop the subscriptions table
        Schema::dropIfExists('subscriptions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate subscriptions table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('field_area_id');
            $table->uuid('plan_id');
            $table->decimal('price_per_hectare', 10, 2);
            $table->decimal('total_price', 30, 2);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'expired', 'cancelled', 'trial', 'awaiting_payment', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('field_area_id')->references('id')->on('field_areas')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
        });

        // Re-add the foreign key constraint to payments table
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'subscription_id')) {
                $table->uuid('subscription_id')->after('id');
                $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
            }
        });
    }
};

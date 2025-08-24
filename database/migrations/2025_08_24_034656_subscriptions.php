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
            $table->uuid('user_id'); // Foreign key ke tabel users
            $table->uuid('region_id'); // Foreign key ke tabel regions
            $table->timestamp('start_date'); // Tanggal mulai langganan
            $table->timestamp('end_date'); // Tanggal berakhir langganan
            $table->decimal('price_per_hectare', 10, 2)->default(1000.00); // Harga per hektar saat langganan dibuat
            $table->decimal('total_price', 12, 2); // Total harga (luas_hektar * harga_per_hektar)
            $table->enum('status', ['active', 'pending', 'expired', 'cancelled'])->default('pending'); // Status langganan

            $table->timestamp('created_at')->useCurrent()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('region_id')->references('id')->on('regions')->onDelete('cascade');
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

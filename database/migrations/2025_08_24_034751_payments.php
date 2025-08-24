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
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('subscription_id'); // Foreign key ke tabel subscriptions
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('amount', 12, 2); // Jumlah yang dibayar
            $table->timestamp('payment_date'); // Tanggal pembayaran
            $table->string('payment_method')->nullable(); // Metode pembayaran (misal: "Transfer Bank", "E-Wallet")
            $table->string('transaction_id')->nullable()->unique(); // ID transaksi dari gateway pembayaran
            $table->enum('status', ['completed', 'pending', 'failed'])->default('pending'); // Status pembayaran
            $table->string('sender_bank')->nullable(); // Nama bank pengirim
            $table->string('sender_name_bank')->nullable(); // Nama pemilik rekening pengirim
            $table->string('sender_bank_number')->nullable(); // Nomor rekening pengirim

            $table->timestamp('created_at')->useCurrent()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

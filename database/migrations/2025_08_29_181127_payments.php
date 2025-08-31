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
            $table->uuid('subscription_id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('amount', 30, 2);
            $table->string('currency', 10)->default('USD');
            $table->enum('status', ['pending', 'waiting_verification', 'paid', 'failed', 'refunded', 'chargeback'])->default('pending');
            $table->timestamp('due_date')->nullable(); // tanggal batas akhir pembayaran

            // Kolom untuk manual transfer
            $table->string('bank_name')->nullable(); // BCA, Mandiri, dll
            $table->string('account_name')->nullable(); // nama pemilik rekening
            $table->string('account_number')->nullable(); // no rek tujuan
            $table->string('proof_image')->nullable(); // path bukti transfer
            $table->timestamp('verified_at')->nullable(); // kapan diverifikasi admin
            $table->uuid('verified_by')->nullable(); // admin yang verifikasi

            // Kolom untuk integrasi payment gateway (future proof)
            $table->string('payment_method')->nullable(); // manual, stripe, midtrans, dll
            $table->string('transaction_ref')->nullable(); // reference id dari gateway
            $table->timestamp('paid_at')->nullable(); // waktu pembayaran berhasil

            $table->timestamps();

            $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
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

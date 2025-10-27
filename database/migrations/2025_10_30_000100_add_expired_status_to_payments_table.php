<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'waiting_verification', 'paid', 'failed', 'refunded', 'chargeback', 'expired') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('pending', 'waiting_verification', 'paid', 'failed', 'refunded', 'chargeback') DEFAULT 'pending'");
    }
};

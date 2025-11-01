<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'gateway_token')) {
                $table->string('gateway_token')->nullable()->after('transaction_ref');
            }

            if (!Schema::hasColumn('payments', 'gateway_payload')) {
                $table->json('gateway_payload')->nullable()->after('gateway_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'gateway_payload')) {
                $table->dropColumn('gateway_payload');
            }

            if (Schema::hasColumn('payments', 'gateway_token')) {
                $table->dropColumn('gateway_token');
            }
        });
    }
};

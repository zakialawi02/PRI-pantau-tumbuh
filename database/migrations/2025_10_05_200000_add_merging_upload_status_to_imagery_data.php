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
        DB::statement("ALTER TABLE imagery_data MODIFY upload_status ENUM('pending','uploading','merging','done','failed') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('imagery_data')
            ->where('upload_status', 'merging')
            ->update(['upload_status' => 'pending']);

        DB::statement("ALTER TABLE imagery_data MODIFY upload_status ENUM('pending','uploading','done','failed') DEFAULT 'pending'");
    }
};

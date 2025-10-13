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
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->string('chunk_id')->nullable()->after('path');
            $table->unsignedInteger('chunk_total')->nullable()->after('chunk_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->dropColumn(['chunk_id', 'chunk_total']);
        });
    }
};

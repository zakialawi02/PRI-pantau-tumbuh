<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->json('geoserver_bounds')->nullable()->after('geoserver_layer_name');
            $table->json('processed_geoserver_bounds')->nullable()->after('processed_geoserver_layer_name');
        });
    }

    public function down(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->dropColumn(['geoserver_bounds', 'processed_geoserver_bounds']);
        });
    }
};

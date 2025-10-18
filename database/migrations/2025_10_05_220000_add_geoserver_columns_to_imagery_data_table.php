<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->string('geoserver_store_name')->nullable()->after('path');
            $table->string('geoserver_layer_name')->nullable()->after('geoserver_store_name');
            $table->string('processed_geoserver_store_name')->nullable()->after('processed_path');
            $table->string('processed_geoserver_layer_name')->nullable()->after('processed_geoserver_store_name');
        });
    }

    public function down(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->dropColumn([
                'geoserver_store_name',
                'geoserver_layer_name',
                'processed_geoserver_store_name',
                'processed_geoserver_layer_name',
            ]);
        });
    }
};

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
            $table->string('geoserver_store')->nullable()->after('processed_path');
            $table->string('geoserver_layer')->nullable()->after('geoserver_store');
            $table->string('geoserver_wms_url')->nullable()->after('geoserver_layer');
            $table->json('geoserver_wms_params')->nullable()->after('geoserver_wms_url');
            $table->string('geoserver_wmts_url')->nullable()->after('geoserver_wms_params');
            $table->string('geoserver_wmts_layer')->nullable()->after('geoserver_wmts_url');
            $table->json('geoserver_native_bbox')->nullable()->after('geoserver_wmts_layer');
            $table->json('geoserver_latlon_bbox')->nullable()->after('geoserver_native_bbox');
            $table->timestamp('geoserver_published_at')->nullable()->after('geoserver_latlon_bbox');
            $table->text('geoserver_error')->nullable()->after('geoserver_published_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->dropColumn([
                'geoserver_store',
                'geoserver_layer',
                'geoserver_wms_url',
                'geoserver_wms_params',
                'geoserver_wmts_url',
                'geoserver_wmts_layer',
                'geoserver_native_bbox',
                'geoserver_latlon_bbox',
                'geoserver_published_at',
                'geoserver_error',
            ]);
        });
    }
};

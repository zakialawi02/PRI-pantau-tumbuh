<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->string('geoserver_workspace')->nullable()->after('processed_at');
            $table->string('source_geoserver_store')->nullable()->after('geoserver_workspace');
            $table->string('source_geoserver_layer')->nullable()->after('source_geoserver_store');
            $table->string('source_wms_url')->nullable()->after('source_geoserver_layer');
            $table->string('source_wmts_url')->nullable()->after('source_wms_url');
            $table->json('source_bbox')->nullable()->after('source_wmts_url');
            $table->string('processed_geoserver_store')->nullable()->after('source_bbox');
            $table->string('processed_geoserver_layer')->nullable()->after('processed_geoserver_store');
            $table->string('processed_wms_url')->nullable()->after('processed_geoserver_layer');
            $table->string('processed_wmts_url')->nullable()->after('processed_wms_url');
            $table->json('processed_bbox')->nullable()->after('processed_wmts_url');
        });
    }

    public function down(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->dropColumn([
                'geoserver_workspace',
                'source_geoserver_store',
                'source_geoserver_layer',
                'source_wms_url',
                'source_wmts_url',
                'source_bbox',
                'processed_geoserver_store',
                'processed_geoserver_layer',
                'processed_wms_url',
                'processed_wmts_url',
                'processed_bbox',
            ]);
        });
    }
};

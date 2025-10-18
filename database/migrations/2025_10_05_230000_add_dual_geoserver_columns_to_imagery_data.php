<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->string('geoserver_source_store')->nullable()->after('geoserver_published_at');
            $table->string('geoserver_source_layer')->nullable()->after('geoserver_source_store');
            $table->json('geoserver_source_bbox')->nullable()->after('geoserver_source_layer');
            $table->timestamp('geoserver_source_published_at')->nullable()->after('geoserver_source_bbox');

            $table->string('geoserver_processed_store')->nullable()->after('geoserver_source_published_at');
            $table->string('geoserver_processed_layer')->nullable()->after('geoserver_processed_store');
            $table->json('geoserver_processed_bbox')->nullable()->after('geoserver_processed_layer');
            $table->timestamp('geoserver_processed_published_at')->nullable()->after('geoserver_processed_bbox');
        });
    }

    public function down(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->dropColumn([
                'geoserver_source_store',
                'geoserver_source_layer',
                'geoserver_source_bbox',
                'geoserver_source_published_at',
                'geoserver_processed_store',
                'geoserver_processed_layer',
                'geoserver_processed_bbox',
                'geoserver_processed_published_at',
            ]);
        });
    }
};

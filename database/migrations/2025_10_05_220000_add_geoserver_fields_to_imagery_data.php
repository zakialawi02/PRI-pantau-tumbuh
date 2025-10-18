<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->string('geoserver_store')->nullable()->after('processed_path');
            $table->string('geoserver_layer')->nullable()->after('geoserver_store');
            $table->json('geoserver_bbox')->nullable()->after('geoserver_layer');
            $table->timestamp('geoserver_published_at')->nullable()->after('geoserver_bbox');
        });
    }

    public function down(): void
    {
        Schema::table('imagery_data', function (Blueprint $table) {
            $table->dropColumn(['geoserver_store', 'geoserver_layer', 'geoserver_bbox', 'geoserver_published_at']);
        });
    }
};

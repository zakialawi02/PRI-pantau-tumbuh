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
        Schema::create('regions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id'); // Foreign key ke tabel users
            $table->string('name')->nullable(); // Nama opsional untuk wilayah (misal: "Sawah Kebun A")
            $table->text('geometry'); // Data geografis wilayah dalam format GeoJSON (Polygon)
            $table->decimal('area_hectares', 10, 4); // Luas wilayah dalam hektar, presisi 4 desimal
            $table->timestamp('created_at')->useCurrent()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
            $table->softDeletes(); // Untuk soft delete wilayah

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};

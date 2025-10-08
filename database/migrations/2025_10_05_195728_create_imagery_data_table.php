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
        Schema::create('imagery_data', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');

            $table->string('source_type');
            $table->string('original_name');
            $table->string('stored_name');
            $table->decimal('size', 20, 2)->default(0);
            $table->string('format', 10);
            $table->string('path');

            // hasil setelah diproses
            $table->string('processed_path')->nullable();
            $table->timestamp('processed_at')->nullable();

            // status tracking
            $table->enum('upload_status', ['pending', 'uploading', 'done', 'failed'])
                ->default('pending')
                ->comment('Track file upload progress');
            $table->enum('processing_status', ['skip', 'waiting', 'processing', 'completed', 'error', 'cancelled'])
                ->default('waiting')
                ->comment('Track Python processing state');

            // scheduling for deletion
            $table->timestamp('scheduled_deletion_at')->nullable()
                ->comment('The date when the original imagery is scheduled to be deleted');

            $table->timestamp('uploaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imagery_data');
    }
};

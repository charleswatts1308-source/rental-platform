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
        Schema::create('file_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rental_id');
            $table->string('file_name', 255);
            $table->string('blob_url', 500); // Will change to file_path for local storage
            $table->string('content_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->timestamp('uploaded_date');

            // Foreign key constraint
            $table->foreign('rental_id')->references('rental_id')->on('rentals')->onDelete('cascade');

            // Indexes for common queries
            $table->index('rental_id');
            $table->index('uploaded_date');
            $table->index('content_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('file_attachments');
    }
};

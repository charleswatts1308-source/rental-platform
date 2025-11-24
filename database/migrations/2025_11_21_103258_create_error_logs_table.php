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
        Schema::create('error_logs', function (Blueprint $table) {
            $table->id('log_id');
            $table->string('user_id', 450)->nullable(); // Matches ASP.NET Identity length
            $table->string('error_type', 100)->default('');
            $table->string('error_category', 200)->default('');
            $table->string('error_message', 1000)->default('');
            $table->string('page_url', 500)->nullable();
            $table->string('entity_type', 100)->nullable();
            $table->integer('entity_id')->nullable();
            $table->string('additional_data', 2000)->nullable();
            $table->timestamp('error_date');

            // Indexes for error analysis
            $table->index('error_date');
            $table->index('error_type');
            $table->index(['entity_type', 'entity_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_logs');
    }
};

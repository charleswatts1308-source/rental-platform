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
        Schema::create('page_views', function (Blueprint $table) {
            $table->id('page_view_id');
            $table->timestamp('view_date_time');
            $table->string('page_name', 500);
            $table->string('ip_address', 500);
            $table->string('user_agent', 1000);
            $table->string('cookies', 2000);

            // Indexes for analytics queries
            $table->index('view_date_time');
            $table->index('page_name');
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};

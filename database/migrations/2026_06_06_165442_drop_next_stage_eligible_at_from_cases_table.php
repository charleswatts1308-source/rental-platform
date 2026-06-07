<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Drop the compound index first — MySQL/MariaDB require
            // index drop before its columns are removed.
            $table->dropIndex(['status', 'next_stage_eligible_at']);
            $table->dropColumn('next_stage_eligible_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->timestamp('next_stage_eligible_at')->nullable()->after('current_stage');
            $table->index(['status', 'next_stage_eligible_at']);
        });
    }
};

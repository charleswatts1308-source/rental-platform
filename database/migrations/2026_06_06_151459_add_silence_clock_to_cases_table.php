<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // Silence-model clock columns — written by SendCaseNotice
            // (ball=landlord) and HandleInboundReply (ball=tenant).
            // Read only by silence:sweep (shadow mode in this phase).
            // Old code paths do not read these columns.
            $table->string('ball_with', 8)
                ->nullable()
                ->after('current_stage');

            $table->timestamp('silence_clock_started_at')
                ->nullable()
                ->after('ball_with');

            // Frozen at clock-start per D4 in-flight guardrail.
            // Stores the five settings keys (escalation.interval_days,
            // escalation.max_notices, nudge.first_days, nudge.second_days,
            // nudge.dormancy_days) so a mid-flight settings edit cannot
            // retro-fire or retro-defer letters on in-flight clocks.
            // Written once at clock start; never edited in place.
            $table->json('silence_settings_snapshot')
                ->nullable()
                ->after('silence_clock_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn([
                'ball_with',
                'silence_clock_started_at',
                'silence_settings_snapshot',
            ]);
        });
    }
};

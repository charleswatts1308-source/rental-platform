<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('silence_shadow_log', function (Blueprint $table) {
            // True when this verdict was actually performed (landlord-side
            // send_escalation only, in 2b). False for all shadow intents,
            // no_action rows, pretend-mode rows, exhausted_intent, and
            // races/skips. Default false; no backfill needed (Phase 2a
            // rows are dev-only).
            $table->boolean('executed')->default(false)->after('intended_action');
        });
    }

    public function down(): void
    {
        Schema::table('silence_shadow_log', function (Blueprint $table) {
            $table->dropColumn('executed');
        });
    }
};

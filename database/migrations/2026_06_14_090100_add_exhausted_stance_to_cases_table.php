<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D14 (Phase 4) — adds cases.exhausted_stance: the tenant's COSMETIC framing
 * of an escalation_exhausted case (null | abandoned | unresolved).
 *
 * Label only. Read by the UI and by NOTHING in the sweep, verdict, clock, or
 * state machine — it is structurally impossible for the stance to affect
 * machine behaviour (no decision code references the column). Default NULL:
 * the tenant is never forced to choose.
 *
 * Nullable string rather than a DB ENUM — the value set lives in
 * App\Enums\ExhaustedStance and is enforced at the PHP/validation layer,
 * matching the project's preference for keeping value sets in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('exhausted_stance')->nullable()->after('landlord_engaged');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('exhausted_stance');
        });
    }
};

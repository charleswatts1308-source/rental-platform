<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D11 — adds cases.dormant_at: the moment the case transitioned to
 * dormant. Anchors the dormancy revival window (D11) so that a tenant
 * reply within `dormancy.revival_days` revives the case, and beyond
 * the window the page withdraws the reply action.
 *
 * Written by RepairCase::applyColumnSideEffects on the
 * AwaitingTenantReview/AwaitingLandlord/OnHold → Dormant transition
 * (the silence:sweep's tenant-side dormancy fire) — set to now().
 * Cleared when the case is revived via tenant reply.
 *
 * Per Correction 2 of the Phase 3 D0 review: anchoring on a dedicated
 * column rather than reading the most-recent case_dormant case_event
 * row, because the column is unambiguous and survives event-vocabulary
 * shifts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->timestamp('dormant_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('dormant_at');
        });
    }
};

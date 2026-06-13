<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D15 — adds cases.landlord_engaged: a one-way fact recording whether
 * this landlord has ever replied on this case.
 *
 * Governs engagement-gated escalation (design doc D15):
 *   - false (never engaged) → escalation stays fully automatic; the
 *     tenant is notified on every auto-send (D0.6 notify-on-send).
 *   - true  (engaged-then-quiet) → escalation is WITHHELD and surfaced
 *     for tenant authorisation; the tenant is nudged to authorise.
 *
 * Flipped false→true in HandleInboundReply::execute() on ANY
 * token-resolved inbound (Ruling 1 — generous definition fails safe:
 * a wrongly-engaged case under-pursues, never over-pursues). The flip
 * is idempotent and never resets.
 *
 * Default false. Pilot is migrate:fresh from files, so every case is
 * created with the flag and flipped by the inbound handler — nothing to
 * backfill. If live cases ever existed, the flag is derivable:
 * EXISTS(case_messages WHERE direction=inbound AND sender_role=landlord).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->boolean('landlord_engaged')->default(false)->after('ball_with');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('landlord_engaged');
        });
    }
};

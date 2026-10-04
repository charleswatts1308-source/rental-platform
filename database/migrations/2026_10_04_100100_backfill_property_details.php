<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * STEP 2 OF 3 — see the step-1 migration for why this is separate.
 *
 * Every property that pre-dates the question gets `not_specified` on
 * both columns, NOT `other` and NOT `no`.
 *
 * This is the whole reason the two values exist. The field is there to
 * be counted, and "the tenant chose Other" has to stay separable from
 * "we never asked them". Backfill the two together and the statistics
 * are wrong from the first day in a way nothing later can unpick,
 * because the information was never recorded.
 *
 * Literals, not enum references: a migration records what ran, and must
 * keep running the same way if the enum is later renamed or a case is
 * removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('properties')
            ->whereNull('property_type')
            ->update(['property_type' => 'not_specified']);

        DB::table('properties')
            ->whereNull('has_lease_agreement')
            ->update(['has_lease_agreement' => 'not_specified']);
    }

    /**
     * Reverses only what this migration did. A row a tenant has since
     * answered is left alone — rolling back a backfill must not discard
     * a real answer.
     */
    public function down(): void
    {
        DB::table('properties')
            ->where('property_type', 'not_specified')
            ->update(['property_type' => null]);

        DB::table('properties')
            ->where('has_lease_agreement', 'not_specified')
            ->update(['has_lease_agreement' => null]);
    }
};

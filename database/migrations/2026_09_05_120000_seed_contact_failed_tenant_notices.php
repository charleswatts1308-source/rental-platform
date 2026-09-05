<?php

use Database\Seeders\LetterTemplateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #25 — seeds the two contact_failed tenant notification templates on
 * environments that already exist.
 *
 * LetterTemplateSeeder carries them for a fresh build, but every
 * long-lived box (gafol, prod) was seeded before they were written, and
 * the seeder is not re-run on deploy. Without this the active-row idiom
 * does exactly what it is designed to do — no active row, no send — and
 * a stopped case would notify nobody. Silent, and correct by its own
 * rules, which is the worst kind of gap.
 *
 * Mirrors 2026_08_09_120000_seed_attachments_first_notice_max_setting:
 * data only, no schema change, so no #18 MariaDB check applies.
 *
 * Idempotent both ways. The seeder uses updateOrCreate on `code`, so
 * running this where the rows already exist refreshes them rather than
 * duplicating.
 */
return new class extends Migration
{
    private const CODES = [
        'contact_failed_bounce',
        'contact_failed_complaint',
    ];

    public function up(): void
    {
        (new LetterTemplateSeeder)->run();
    }

    public function down(): void
    {
        // Only the two rows this migration exists for. The seeder touches
        // every template, but rolling back must not remove templates that
        // predate it.
        DB::table('letter_templates')->whereIn('code', self::CODES)->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Button policy (docs/button-policy.md, 18 Oct 2026) — email buttons move
 * from #2563eb (Tailwind blue) to the logo green #047857, matching the
 * site's primary buttons.
 *
 * Long-lived boxes hold their template rows in the database (seeded long
 * ago, some since edited through the template editor), so changing the
 * seeder alone would reach fresh builds only. This swaps the one colour
 * string in place and touches nothing else, so edited wording survives.
 *
 * Styling, not wording, so it writes no letter_text_change_history row.
 * Outbound letters already sent are frozen on case_messages and are not
 * affected — this changes future sends only.
 *
 * Data only, no schema change, so no #18 MariaDB check applies.
 */
return new class extends Migration
{
    private const FROM = 'background: #2563eb';

    private const TO = 'background: #047857';

    public function up(): void
    {
        DB::table('letter_templates')
            ->where('body', 'like', '%'.self::FROM.'%')
            ->update(['body' => DB::raw("REPLACE(body, '".self::FROM."', '".self::TO."')")]);
    }

    public function down(): void
    {
        DB::table('letter_templates')
            ->where('body', 'like', '%'.self::TO.'%')
            ->update(['body' => DB::raw("REPLACE(body, '".self::TO."', '".self::FROM."')")]);
    }
};

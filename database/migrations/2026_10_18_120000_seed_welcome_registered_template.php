<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * #81 — seeds the welcome_registered template on environments that
 * already exist. LetterTemplateSeeder carries it for a fresh build.
 *
 * Same shape as 2026_09_05_120000_seed_contact_failed_tenant_notices, and
 * for the same reasons: it does not call the seeder (which would overwrite
 * wording edited through the template editor), it inserts only if absent,
 * and the body is duplicated here deliberately so this historical record
 * never depends on what the seeder says later.
 *
 * Data only, no schema change, so no #18 MariaDB check applies.
 */
return new class extends Migration
{
    private const CODE = 'welcome_registered';

    public function up(): void
    {
        if (DB::table('letter_templates')->where('code', self::CODE)->exists()) {
            return;
        }

        DB::table('letter_templates')->insert([
            'code' => self::CODE,
            'description' => '#81 — "Thanks for registering", sent once on the Verified event. Account-level, no case, mail-only (never a case_messages row). Active-row idiom.',
            'type' => 'account',
            'stage' => null,
            'subject' => 'Thanks for registering with renters.rent',
            'body' => $this->body(),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('letter_templates')->where('code', self::CODE)->delete();
    }

    private function body(): string
    {
        return <<<'HTML'
<p>Hello {{tenant_name}},</p>

<p>Thanks for registering with renters.rent.</p>

<p>When something in your home needs fixing, we help you put it in writing. Tell us what needs repairing and who your landlord is, and we send them a formal, dated repair notice and keep a record of everything that follows.</p>

<p>You can start a repair case at any time: <a href="{{start_case_url}}">{{start_case_url}}</a></p>

<p>renters.rent</p>
HTML;
    }
};

<?php

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
 * IT DOES NOT CALL THE SEEDER. An earlier version did, and that was
 * wrong: LetterTemplateSeeder does updateOrCreate across EVERY template,
 * so running it here would overwrite wording edited through the D16
 * template editor on that box. letter_text_change_history exists
 * precisely because those edits are expected. This inserts its own two
 * rows and touches nothing else.
 *
 * The bodies are duplicated from the seeder deliberately. A migration is
 * a historical record and must not depend on what the seeder happens to
 * say later; the two are free to diverge, and editors are free to
 * improve the live rows without this file ever reaching back.
 *
 * Data only, no schema change, so no #18 MariaDB check applies. Mirrors
 * 2026_08_09_120000_seed_attachments_first_notice_max_setting.
 */
return new class extends Migration
{
    private const CODES = [
        'contact_failed_bounce',
        'contact_failed_complaint',
    ];

    public function up(): void
    {
        foreach ($this->rows() as $row) {
            // Insert-if-absent. A box that already has the row — because it
            // was built fresh from the seeder — keeps whatever it has,
            // including any edits made since.
            $exists = DB::table('letter_templates')->where('code', $row['code'])->exists();

            if ($exists) {
                continue;
            }

            DB::table('letter_templates')->insert($row + [
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('letter_templates')->whereIn('code', self::CODES)->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(): array
    {
        return [
            [
                'code' => 'contact_failed_bounce',
                'description' => 'Tenant notification (#25 / D17.2) fired when a letter PERMANENTLY fails to deliver. The case stops at contact_failed. Active-row idiom.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => 'Your notice could not be delivered — case {{case_reference}}',
                'body' => $this->bounceBody(),
            ],
            [
                'code' => 'contact_failed_complaint',
                'description' => 'Tenant notification (#25 / D17.5) fired when a letter is reported as spam. Distinct from the bounce notice: the letter ARRIVED, so there is no address to correct and no fork.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => 'Your notice was reported as spam — case {{case_reference}}',
                'body' => $this->complaintBody(),
            ],
        ];
    }

    private function bounceBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>We were not able to deliver your repair notice to {{failed_address}}. The message was rejected by the receiving mail server, which means <strong>{{landlord_name}} has not been sent it</strong>.</p>

<p>We have stopped this case rather than carrying on. Our notices work by recording that your landlord was contacted and did not respond — and that would not be true here, so we will not send further letters to an address we know does not work.</p>

<p>Everything already on the case stays on record: what we sent, when we sent it, and that it could not be delivered.</p>

<p><strong>What to do next.</strong> Check the landlord email address on the property. If it is wrong, correct it and raise a new case — the corrected address will be used from then on. If it looks right, it is worth confirming it with your landlord or letting agent directly.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function complaintBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>Your repair notice was delivered to {{landlord_name}} and then reported as spam by the recipient's mail provider.</p>

<p>We have stopped this case. Once an address reports our messages as spam we will not keep sending to it — continuing would put every other tenant's notices at risk of being blocked too.</p>

<p>This is worth knowing: it means the notice <strong>did arrive</strong>. Everything on the case stays on record, including the delivery and the report, and that record is yours to use.</p>

<p>If the repair is still outstanding, contacting your landlord or letting agent by another route — post, phone, or through your tenancy agreement's stated contact — is the next step.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }
};

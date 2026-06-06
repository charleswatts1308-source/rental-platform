<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the v1 letter templates per silence-model design D1, D2, D5.
 *
 * Only ONE escalation template is seeded — `landlord_wakeup_generic`
 * (type=escalation, stage=NULL). This single row serves notice numbers
 * 1..N via the renderer's fallback rule:
 *   active escalation with stage=N → else active stage=NULL.
 *
 * Graduated per-stage letters can be reintroduced later by inserting
 * additional `escalation` rows with non-null `stage` values; no code
 * change required. That is the point of the table-driven design.
 *
 * Templates 2-4 are seeded NOW but NOT wired by Phase 1 — their
 * send-points arrive in Phases 2 (nudge) and 4 (exhaustion). Wiring
 * them here would constitute behaviour change, which Phase 1
 * explicitly forbids.
 *
 * Idempotent: existing codes are updated; new codes are inserted.
 * Editing path for production tuning is phpMyAdmin (the admin CRUD
 * is Phase 5).
 */
class LetterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            LetterTemplate::updateOrCreate(
                ['code' => $template['code']],
                $template + ['active' => true],
            );
        }
    }

    /**
     * @return array<int, array{code: string, description: string, type: string, stage: ?int, subject: string, body: string}>
     */
    private function templates(): array
    {
        return [
            [
                'code' => 'landlord_wakeup_generic',
                'description' => 'Generic landlord escalation wake-up — covers notice numbers 1..N via fallback when no per-stage row is active.',
                'type' => 'escalation',
                'stage' => null,
                'subject' => 'Repair issue notice {{notice_number}} — {{property_address}} (case {{case_reference}})',
                'body' => $this->landlordWakeupBody(),
            ],
            [
                'code' => 'tenant_nudge_generic',
                'description' => 'Private tenant nudge when the ball is in the tenant\'s court and silence is building — supportive, not in the landlord-facing thread.',
                'type' => 'tenant_nudge',
                'stage' => null,
                'subject' => 'Your repair case {{case_reference}} — just a nudge',
                'body' => $this->tenantNudgeBody(),
            ],
            [
                'code' => 'exhaustion_landlord_closer',
                'description' => 'One-shot landlord closer at escalation_exhausted — sober, signals the matter has moved past private correspondence.',
                'type' => 'exhaustion_landlord',
                'stage' => null,
                'subject' => 'Repair issue at {{property_address}} — case {{case_reference}}: closing correspondence',
                'body' => $this->exhaustionLandlordBody(),
            ],
            [
                'code' => 'tenant_exhaustion_notice',
                'description' => 'Tenant notification when the escalation ladder is exhausted — explains the state and points to the case page for next steps.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => 'Your repair case {{case_reference}} — the escalation process has run its course',
                'body' => $this->tenantExhaustionBody(),
            ],
        ];
    }

    private function landlordWakeupBody(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Repair issue notice</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Dear {{landlord_name}},</p>

<p>I am writing to formally notify you of a repair issue at the rental property below. This is notice {{notice_number}} regarding case reference {{case_reference}}.</p>

<p>
  <strong>Property:</strong> {{property_address}}<br>
  <strong>Case reference:</strong> {{case_reference}}
</p>

<p><strong>My description of the issue:</strong></p>
<blockquote style="border-left: 3px solid #ccc; padding-left: 12px; margin-left: 0; color: #444;">
  {{issue_description}}
</blockquote>

<p>Under section 11 of the Landlord and Tenant Act 1985, you have a duty to keep in repair the structure and exterior of the property and to keep in proper working order the installations for the supply of water, gas, electricity and sanitation, and for space heating and water heating.</p>

<p>Please confirm receipt of this notice and let me know within {{response_days}} days when you intend to inspect and carry out the necessary works. You can reply to this email and your reply will be linked to this case automatically.</p>

<p>Yours faithfully,<br>
{{tenant_name}}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. Replies are routed back to the tenant via the system; please reply to this email rather than emailing the tenant directly. The tenant's contact details are kept private to protect against retaliation.
</p>

</body>
</html>
HTML;
    }

    private function tenantNudgeBody(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your repair case — a quick nudge</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Hi {{tenant_name}},</p>

<p>This is a quiet nudge about your repair case <strong>{{case_reference}}</strong> at {{property_address}}.</p>

<p>The case is waiting on you for the next step. If you'd like to keep things moving, log in to your renters.rent dashboard to pick it up. If you no longer need to pursue this, you can mark it resolved or pause it from the same place.</p>

<p>If we don't hear back, we'll send one more nudge, then mark the case dormant — but a reply from you at any time picks it straight back up.</p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>

</body>
</html>
HTML;
    }

    private function exhaustionLandlordBody(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Closing correspondence</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Dear {{landlord_name}},</p>

<p>This is a final notice regarding the unresolved repair issue at {{property_address}} (case reference {{case_reference}}).</p>

<p>Repeated formal notices have been sent through this service over a sustained period without resolution. As a result, this matter is now being pursued through external channels open to the tenant, which may include reporting to the local authority's environmental health team, the relevant property ombudsman, or court action under section 11 of the Landlord and Tenant Act 1985.</p>

<p>The full correspondence record on this case is available as evidence to any of these bodies.</p>

<p>Yours faithfully,<br>
{{tenant_name}}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. The tenant's contact details are kept private to protect against retaliation.
</p>

</body>
</html>
HTML;
    }

    private function tenantExhaustionBody(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your repair case — next steps</title>
</head>
<body style="font-family: Arial, sans-serif; font-size: 14px; color: #222; line-height: 1.5;">

<p>Hi {{tenant_name}},</p>

<p>Your repair case <strong>{{case_reference}}</strong> at {{property_address}} has reached the end of the escalation process. The landlord has not responded across the full sequence of formal notices.</p>

<p>Log in to your dashboard to see your options from here — external routes available to you include the local authority's environmental health team, the relevant property ombudsman, and court action. The full correspondence record on your case is ready to be shared with any of them as evidence.</p>

<p>renters.rent does not act on your behalf with these bodies — the decision and the next step are yours.</p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>

</body>
</html>
HTML;
    }
}

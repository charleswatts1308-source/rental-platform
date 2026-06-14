<?php

namespace Database\Seeders;

use App\Models\LetterTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the v1 letter templates per silence-model design D1, D2, D5,
 * D9, D11, D12, D13.
 *
 * Only ONE escalation template is seeded — `landlord_wakeup_generic`
 * (type=escalation, stage=NULL). This single row serves notice numbers
 * 1..N via the renderer's fallback rule:
 *   active escalation with stage=N → else active stage=NULL.
 *
 * Graduated per-stage letters can be reintroduced later by inserting
 * additional `escalation` rows with non-null `stage` values; no code
 * change required.
 *
 * Phase 3 changes:
 *   - Bodies stripped of <doctype>/<html>/<body> wrappers and the
 *     inline property/reference/description block. The renderer
 *     auto-injects the D9 header at the top of <body> and wraps with
 *     a standard envelope. Templates are now body content only.
 *   - {{magic_link}} placeholder added to tenant-bound bodies (D12).
 *   - New rows: dormancy_transition_notice, hold_expired_notice,
 *     landlord_reply_received_notice (template-rendered replacing
 *     the old blade-rendered mailable), create_case_authorisation
 *     (ui_copy for the D13 create-case form).
 *
 * Idempotent: existing codes are updated; new codes are inserted.
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
                // D14 — fired by SilenceSweep::executeExhaustionTransition via
                // SendExhaustionCloser on the transition into escalation_exhausted
                // (design doc D5). Active-row idiom: present → send; absent → skip.
                // Landlord-facing legal letter → SOLICITOR-GATED for production
                // (draft fine for gafol live-fire), alongside the letter wording.
                'code' => 'exhaustion_landlord_closer',
                'description' => 'D14 — one-shot landlord closer fired on the escalation_exhausted transition (design doc D5). Sober; signals the matter has moved past private correspondence. stage_at_send=NULL so it never counts toward the ladder. SOLICITOR-GATED.',
                'type' => 'exhaustion_landlord',
                'stage' => null,
                'subject' => 'Repair issue at {{property_address}} — case {{case_reference}}: closing correspondence',
                'body' => $this->exhaustionLandlordBody(),
            ],
            [
                // D14 — fired by SilenceSweep::executeExhaustionTransition on the
                // transition into escalation_exhausted. Pre-existing row, reused
                // (not duplicated). Active-row idiom. SOLICITOR-GATED for
                // production wording, alongside the closer + signposting page.
                'code' => 'tenant_exhaustion_notice',
                'description' => 'D14 — tenant notification on the escalation_exhausted transition. Explains the ladder is complete and sends the tenant to the case page, where the signposting (members.escalation-routes) link lives. SOLICITOR-GATED.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => 'Your repair case {{case_reference}} — the escalation process has run its course',
                'body' => $this->tenantExhaustionBody(),
            ],
            [
                'code' => 'auto_escalation_tenant_notice',
                'description' => 'Tenant notification fired by silence:sweep when it auto-escalates a landlord-side case. Active-row idiom: if active, the sweep sends; if not, the sweep skips silently — content edits via phpMyAdmin.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => "We've sent notice {{notice_number}} to your landlord — case {{case_reference}}",
                'body' => $this->autoEscalationTenantNoticeBody(),
            ],
            [
                'code' => 'dormancy_transition_notice',
                'description' => 'Tenant notification fired by silence:sweep on the tenant-side dormancy transition. Active-row idiom — present means send.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => 'Your repair case {{case_reference}} has gone dormant',
                'body' => $this->dormancyTransitionBody(),
            ],
            [
                'code' => 'hold_expired_notice',
                'description' => 'Tenant notification fired by silence:sweep when an OnHold case past hold_until resumes to AwaitingLandlord. Active-row idiom.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => 'The hold on your repair case {{case_reference}} has ended',
                'body' => $this->holdExpiredBody(),
            ],
            [
                'code' => 'landlord_reply_received_notice',
                'description' => 'Tenant notification fired by HandleInboundReply when the landlord replies. Phase 3 replaces the old blade-rendered LandlordReplyReceived mailable.',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => 'Your repair case {{case_reference}} has a new reply',
                'body' => $this->landlordReplyReceivedBody(),
            ],
            [
                'code' => 'create_case_authorisation',
                'description' => 'D13 — one-time authorisation wording shown on the create-case preview before the first letter is sent. ui_copy type: rendered on the web page, not into an email; the header block + envelope wrap are skipped.',
                'type' => 'ui_copy',
                'stage' => null,
                'subject' => 'You are about to send notice 1 to your landlord',
                'body' => $this->createCaseAuthorisationBody(),
            ],
            [
                // D15 — wording signed off (Charlie) for gafol + pilot; NOT
                // solicitor-gated (its sibling escalation_authorisation is).
                // {{last_reply_date}} = date of the landlord's most recent
                // inbound reply, passed by SilenceSweep::dispatchAuthorisationNudge
                // (NOT the latest message — that may be the tenant's own reply).
                'code' => 'authorisation_required_nudge',
                'description' => 'D15 — private tenant nudge fired by silence:sweep when an ENGAGED landlord has gone quiet and the next escalation notice is being WITHHELD pending tenant authorisation. Active-row idiom. Points at the authorise action (magic link).',
                'type' => 'tenant_notification',
                'stage' => null,
                'subject' => "Your landlord hasn't replied — ready to send the next notice?",
                'body' => $this->authorisationRequiredNudgeBody(),
            ],
            [
                // D15 — DRAFT WORDING. This is per-send CONSENT to send a
                // formal legal letter in the tenant's name: SOLICITOR PASS
                // wanted before go-live, alongside Charlie's eyes.
                'code' => 'escalation_authorisation',
                'description' => 'D15 — authorisation wording shown on the authorise-escalation screen (engaged-then-quiet held notice). ui_copy type: rendered on the web page, header block + envelope wrap skipped.',
                'type' => 'ui_copy',
                'stage' => null,
                'subject' => 'You are about to send notice {{notice_number}} to your landlord',
                'body' => $this->escalationAuthorisationBody(),
            ],
        ];
    }

    private function authorisationRequiredNudgeBody(): string
    {
        return <<<'HTML'
<p>Your landlord hasn't responded on {{case_reference}} since their last reply on {{last_reply_date}}.</p>

<p>The next notice is prepared and ready. Because your landlord has engaged with this case before, we don't send further notices automatically — it's your call whether to send the next one now.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Review and send the next notice &rarr;</a></p>

<p>If your issue has since been resolved, you can leave this here — we won't send anything unless you choose to.</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function escalationAuthorisationBody(): string
    {
        return <<<'HTML'
<p>You opened this case authorising renters.rent to send escalating letters in your name if your landlord did not respond. Your landlord engaged and has since gone quiet, so the next notice is ready.</p>

<ul>
  <li>Notice {{notice_number}} is shown below — preview it as your landlord will see it.</li>
  <li>It goes out signed "{{tenant_name}}, via renters.rent"; the landlord's reply comes back to the case, not to you directly.</li>
  <li>Sending it restarts your landlord's {{response_days}}-day response clock and is recorded on the case as evidence.</li>
  <li>You are in control: you can decline, pause the case, or close it at any time from the case page.</li>
</ul>

<p>Press <strong>Confirm and send notice {{notice_number}}</strong> when you are ready.</p>
HTML;
    }

    private function landlordWakeupBody(): string
    {
        return <<<'HTML'
<p>Dear {{landlord_name}},</p>

<p>I am writing to formally notify you of a repair issue at the rental property identified above. This is notice {{notice_number}} regarding the case.</p>

<p>Under section 11 of the Landlord and Tenant Act 1985, you have a duty to keep in repair the structure and exterior of the property and to keep in proper working order the installations for the supply of water, gas, electricity and sanitation, and for space heating and water heating.</p>

<p>Please confirm receipt of this notice and let me know within {{response_days}} days when you intend to inspect and carry out the necessary works. You can reply to this email and your reply will be linked to this case automatically.</p>

<p>Yours faithfully,<br>
{{tenant_name}}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. Replies are routed back to the tenant via the system; please reply to this email rather than emailing the tenant directly. The tenant's contact details are kept private to protect against retaliation.
</p>
HTML;
    }

    private function tenantNudgeBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>This is a quiet nudge about your repair case. The case is waiting on you for the next step.</p>

<p>If you'd like to keep things moving, log in to your renters.rent dashboard to pick it up. If you no longer need to pursue this, you can mark it resolved or pause it from the same place.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>If we don't hear back, we'll send one more nudge, then mark the case dormant — but a reply from you at any time picks it straight back up.</p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function exhaustionLandlordBody(): string
    {
        return <<<'HTML'
<p>Dear {{landlord_name}},</p>

<p>This is a final notice regarding the unresolved repair issue identified above.</p>

<p>Repeated formal notices have been sent through this service over a sustained period without resolution. As a result, this matter is now being pursued through external channels open to the tenant, which may include reporting to the local authority's environmental health team, the relevant property ombudsman, or court action under section 11 of the Landlord and Tenant Act 1985.</p>

<p>The full correspondence record on this case is available as evidence to any of these bodies.</p>

<p>Yours faithfully,<br>
{{tenant_name}}<br>
<em>via renters.rent</em></p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This message was sent through renters.rent on behalf of the tenant. The tenant's contact details are kept private to protect against retaliation.
</p>
HTML;
    }

    private function tenantExhaustionBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>Your repair case has reached the end of the escalation process. The landlord has not responded across the full sequence of formal notices.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>External routes available to you include the local authority's environmental health team, the relevant property ombudsman, and court action. The full correspondence record on your case is ready to be shared with any of them as evidence.</p>

<p>renters.rent does not act on your behalf with these bodies — the decision and the next step are yours.</p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function autoEscalationTenantNoticeBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>We've sent notice <strong>{{notice_number}}</strong> to {{landlord_name}} on your behalf.</p>

<p>Your last message to your landlord went out more than {{response_days}} days ago, so the silence clock expired and the system escalated automatically. The letter is on the case record.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>If the landlord replies, the clock resets and you'll be notified. If silence continues, the next notice will be scheduled automatically.</p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function dormancyTransitionBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>Your repair case has been marked dormant after a sustained period without activity from your side.</p>

<p>Nothing is lost — a reply from you at any time within the next 90 days will pick the case straight back up and the landlord will be contacted again. Beyond 90 days the case stays on record as evidence, but you'll be invited to raise a new case if the issue is still live.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function holdExpiredBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>The hold you placed on your repair case has ended. The case is now waiting on the landlord again, and the silence clock is running.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>If you need to pause again — for example because the landlord has given a fix date — you can do so from the case page.</p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function landlordReplyReceivedBody(): string
    {
        return <<<'HTML'
<p>Hi {{tenant_name}},</p>

<p>Your landlord has replied to your repair case. The reply is on the case record.</p>

<p><a href="{{magic_link}}" style="display: inline-block; padding: 10px 16px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 4px;">Open this case</a></p>

<p>Read the reply, then choose your next step from the case page: reply with more information, pause the case while the landlord follows through, or close it if the issue is sorted.</p>

<p>Best regards,<br>
The renters.rent team</p>

<hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">

<p style="font-size: 11px; color: #888;">
This is a private message between renters.rent and you as the tenant. It is not part of the landlord-facing case correspondence.
</p>
HTML;
    }

    private function createCaseAuthorisationBody(): string
    {
        return <<<'HTML'
<p>By opening this case you authorise renters.rent to send the escalation letters below to {{landlord_name}} in your name if they do not respond.</p>

<ul>
  <li>Notice 1 is sent right now — preview it below.</li>
  <li>If your landlord does not respond within {{response_days}} days, notice 2 is sent automatically — and so on, up to four notices, each {{response_days}} days apart.</li>
  <li>You will be told every time a letter goes out and can pause the case at any point from the case page.</li>
  <li>Every letter goes out signed "{{tenant_name}}, via renters.rent"; the landlord's reply comes back to the case, not to you directly.</li>
</ul>

<p>Press <strong>Confirm and send notice 1</strong> when you are ready, or <strong>Edit</strong> to go back.</p>
HTML;
    }
}

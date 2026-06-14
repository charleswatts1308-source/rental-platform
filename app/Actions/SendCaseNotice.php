<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\ScanStatus;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\MessageAttachment;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use App\Models\Setting;
use App\Services\LetterTemplateRenderer;
use App\Services\ReplyTokenGenerator;
use App\Services\Silence\SilenceClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;

/**
 * Orchestrates outbound mail in the tenant's name: supersede the
 * active reply token (if any), mint a new one, compose the outbound
 * case_message, render + freeze the letter, queue the mail to the
 * landlord_contact, write peripheral case_events, restart the
 * silence clock, and either transition the case or stay put.
 *
 * Post Phase 3 — three entry shapes, distinguished by status + the
 * `$tenantReplyBody` parameter:
 *
 * - Open (first send, $tenantReplyBody null): peripheral events
 *   token_issued + token_superseded?; transitionTo writes notice_sent
 *   as canonical, advancing to awaiting_landlord. Sender_role=System,
 *   template-rendered escalation letter, stage_at_send=1.
 *
 * - AwaitingLandlord + $tenantReplyBody null = silence-sweep auto-
 *   escalation (NEW in 2b, preserved): peripheral events
 *   auto_escalation_sent + token_issued + token_superseded; NO
 *   transitionTo (status stays awaiting_landlord); case is explicitly
 *   saved to persist the clock-restart + current_stage++ attrs.
 *   Sender_role=System, template-rendered, stage_at_send=current+1.
 *
 * - $tenantReplyBody non-null = tenant reply (NEW in Phase 3, D8):
 *   peripheral events token_issued + token_superseded; transitionTo
 *   writes tenant_replied as canonical (target awaiting_landlord);
 *   if already awaiting_landlord (self-send), explicit save replaces
 *   transition. Sender_role=Tenant, free-form body via renderer's
 *   renderFreeForm path with D9 header wrap, stage_at_send=null
 *   (tenant replies are not escalation letters — the counter
 *   predicate excludes them by sender_role+stage_at_send).
 *
 * Demolished from this action in Phase 3: the `$isEscalation` branch
 * (D7 — tenant-initiated escalation no longer exists; the click is
 * gone; TAR is gone) and the `escalation_confirmed_by_tenant` event
 * that lived there.
 *
 * Every entry path restarts the silence clock (ball=landlord,
 * silence_clock_started_at=now, fresh settings snapshot per D4).
 */
class SendCaseNotice
{
    public function __construct(
        private ReplyTokenGenerator $tokenGenerator,
        private LetterTemplateRenderer $renderer,
    ) {}

    /**
     * @param  array<int, array{disk: string, path: string, original_filename: string, mime_type: string, size_bytes: int}>  $attachmentInputs
     */
    public function execute(
        RepairCase $case,
        ?int $actorUserId = null,
        array $attachmentInputs = [],
        ?string $tenantReplyBody = null,
    ): CaseMessage {
        return DB::transaction(function () use ($case, $actorUserId, $attachmentInputs, $tenantReplyBody) {
            $isTenantReply = $tenantReplyBody !== null;
            $isFirstSend = ! $isTenantReply && $case->status === CaseStatus::Open;
            $isAutoEscalation = ! $isTenantReply && $case->status === CaseStatus::AwaitingLandlord;

            $this->assertEntryStatusAllowed($case, $isTenantReply);

            // Stage_at_send semantics (D3 counter):
            // - escalation sends (system) carry stage_at_send — they're
            //   the rows the counter is derived from.
            // - tenant replies (tenant) carry stage_at_send=null — they
            //   wake the case but never advance the escalation ladder.
            $stageAtSend = $isTenantReply
                ? null
                : ($isFirstSend ? $case->current_stage : $case->current_stage + 1);

            $oldToken = $case->replyTokens()->whereNull('superseded_at')->first();
            if ($oldToken) {
                $oldToken->update([
                    'superseded_at' => now(),
                    'expires_at' => now()->addDays(90),
                ]);
            }

            $newToken = ReplyToken::create([
                'case_id' => $case->id,
                'token' => $this->tokenGenerator->generate(),
                'bound_email' => $case->landlordContact->email,
                'issued_at' => now(),
            ]);

            $message = $case->messages()->create([
                'direction' => MessageDirection::Outbound,
                'sender_role' => $isTenantReply ? SenderRole::Tenant : SenderRole::System,
                'stage_at_send' => $stageAtSend,
                'subject' => null,
                'body_raw' => '',
                'tenant_statement' => null,
                'to_address_raw' => $case->landlordContact->email,
                'sent_at' => now(),
            ]);

            foreach ($attachmentInputs as $info) {
                MessageAttachment::create([
                    'case_message_id' => $message->id,
                    'disk' => $info['disk'],
                    'path' => $info['path'],
                    'original_filename' => $info['original_filename'],
                    'mime_type' => $info['mime_type'],
                    'size_bytes' => $info['size_bytes'],
                    'direction' => MessageDirection::Outbound,
                    'scan_status' => ScanStatus::Skipped,
                ]);
            }

            $caseForVars = $case->fresh()->load(['tenant', 'property', 'landlordContact']);
            $vars = $this->buildLetterVars($caseForVars, $stageAtSend);

            if ($isTenantReply) {
                $rendered = $this->renderer->renderFreeForm(
                    $tenantReplyBody,
                    "Reply on repair case {{case_reference}} from {{tenant_name}}",
                    $vars,
                );

                $message->update([
                    'letter_template_id' => null,
                    'letter_template_updated_at' => null,
                    'subject' => $rendered['subject'],
                    'body_raw' => $rendered['body'],
                ]);
            } else {
                $template = LetterTemplate::forEscalation($stageAtSend);
                if ($template === null) {
                    throw new LogicException(
                        "No active escalation template found for stage {$stageAtSend}. "
                        .'Seed the letter_templates table (LetterTemplateSeeder) or activate a row.'
                    );
                }

                $rendered = $this->renderer->render($template, $vars);

                $message->update([
                    'letter_template_id' => $template->id,
                    'letter_template_updated_at' => $template->updated_at,
                    'subject' => $rendered['subject'],
                    'body_raw' => $rendered['body'],
                ]);
            }

            // Silence-model clock (re)start. Ball flips to landlord, the
            // silence clock restarts, settings snapshot is refreshed
            // (D4 in-flight guardrail). For first-send these are
            // persisted by transitionTo's save() below. For
            // auto-escalation and tenant-reply self-send to
            // awaiting_landlord we save explicitly since there is no
            // transition.
            $case->ball_with = 'landlord';
            $case->silence_clock_started_at = now();
            $case->silence_settings_snapshot = SilenceClock::snapshotCurrentSettings();

            Mail::to($case->landlordContact->email)->queue(new CaseNotice(
                $caseForVars,
                $message->fresh(),
                $newToken,
            ));

            // Peripheral events. The canonical state-change event for
            // first-send is written by transitionTo. For auto-escalation
            // and tenant-reply-self-send there is no transition;
            // auto_escalation_sent / tenant_replied is the canonical
            // record in the audit trail (distinguishing system-initiated
            // sends from tenant-initiated for evidential purposes).
            if ($isAutoEscalation) {
                $case->events()->create([
                    'event_type' => 'auto_escalation_sent',
                    'actor_label' => 'system',
                    'occurred_at' => now(),
                    'meta' => ['stage' => $stageAtSend, 'message_id' => $message->id],
                ]);
            }

            $case->events()->create([
                'event_type' => 'token_issued',
                'actor_label' => 'system',
                'occurred_at' => now(),
                'meta' => ['token_id' => $newToken->id],
            ]);

            if ($oldToken) {
                $case->events()->create([
                    'event_type' => 'token_superseded',
                    'actor_label' => 'system',
                    'occurred_at' => now(),
                    'meta' => ['token_id' => $oldToken->id],
                ]);
            }

            if ($isAutoEscalation) {
                // No transition — status stays awaiting_landlord.
                // Persist clock-restart + current_stage++ explicitly.
                $case->current_stage = $stageAtSend;
                $case->save();
            } elseif ($isTenantReply && $case->status === CaseStatus::AwaitingLandlord) {
                // Tenant-reply self-send (already in awaiting_landlord).
                // No transition. Save the clock-restart attrs and write
                // tenant_replied explicitly as the canonical event for
                // this send, mirroring the transitionTo path's event
                // write for the cross-status cases.
                $case->save();
                $case->events()->create([
                    'event_type' => 'tenant_replied',
                    'actor_user_id' => $actorUserId,
                    'actor_label' => 'tenant',
                    'occurred_at' => now(),
                    'meta' => ['message_id' => $message->id],
                ]);
            } else {
                $case->transitionTo(CaseStatus::AwaitingLandlord, [
                    'actor_user_id' => $actorUserId,
                    'actor_label' => $isTenantReply ? 'tenant' : 'tenant',
                    'meta' => ['message_id' => $message->id],
                ]);
            }

            return $message->fresh();
        });
    }

    private function assertEntryStatusAllowed(RepairCase $case, bool $isTenantReply): void
    {
        if ($isTenantReply) {
            $allowed = [
                CaseStatus::AwaitingTenantReview,
                CaseStatus::AwaitingLandlord,
                CaseStatus::OnHold,
                CaseStatus::Dormant,
                // D14 — a tenant reply revives an exhausted case.
                CaseStatus::EscalationExhausted,
            ];
            if (! in_array($case->status, $allowed, true)) {
                throw new LogicException(
                    'SendCaseNotice tenant-reply branch can only run from '
                    .'AwaitingTenantReview, AwaitingLandlord, OnHold, Dormant, '
                    .'or EscalationExhausted; case is in '.$case->status->value
                );
            }

            return;
        }

        $allowed = [CaseStatus::Open, CaseStatus::AwaitingLandlord];
        if (! in_array($case->status, $allowed, true)) {
            throw new LogicException(
                'SendCaseNotice system-escalation branch can only run from '
                .'Open or AwaitingLandlord; case is in '.$case->status->value
            );
        }
    }

    /**
     * Build the letter-template variables.
     *
     * Whitelist source of truth is LetterTemplateRenderer::WHITELIST.
     * Anything not on that list passes through the renderer as the
     * literal `{{token}}` text, so misspellings are visible.
     *
     * `issue_description` reads `cases.description` per D9 — the
     * tenant's original framing, set at case creation, immutable.
     * The header block renders from this same source.
     *
     * `response_days` reads `escalation.interval_days` from Settings —
     * D4 letter/deadline consistency.
     *
     * `notice_number` is null for tenant replies (they are not
     * escalation letters) and the placeholder renders as empty
     * string in templates that reference it.
     *
     * @return array<string, string|int|null>
     */
    private function buildLetterVars(RepairCase $case, ?int $noticeNumber): array
    {
        return [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordContact->name ?: 'Sir or Madam',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'issue_description' => $case->description,
            'response_days' => (int) Setting::get('escalation.interval_days', 14),
            'notice_number' => $noticeNumber,
            'deadline_date' => null,
        ];
    }

    private function propertyAddress(RepairCase $case): string
    {
        $p = $case->property;

        return implode(', ', array_filter([
            $p->address_line1,
            $p->address_line2,
            $p->city,
            $p->postcode,
        ]));
    }
}

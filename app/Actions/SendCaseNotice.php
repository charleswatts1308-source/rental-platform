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
 * Orchestrates the outbound notice flow: supersede the active reply
 * token (if any), mint a new one, compose the outbound case_message,
 * render + freeze the letter against the active template, queue the
 * mail to the landlord_contact, write peripheral case_events, restart
 * the silence clock, and either transition the case or stay put.
 *
 * Three entry states with distinct post-send shapes (silence-phase-2b):
 *
 * - Open (first send): peripheral events token_issued + token_superseded?;
 *   transitionTo writes notice_sent as canonical, advancing to
 *   awaiting_landlord.
 *
 * - TenantActionRequired (tenant click escalation, D7-preserved):
 *   peripheral events escalation_confirmed_by_tenant + notice_sent +
 *   token_issued + token_superseded; transitionTo writes stage_advanced
 *   as canonical, advancing to awaiting_landlord and bumping
 *   current_stage++.
 *
 * - AwaitingLandlord (silence sweep auto-escalation, NEW in 2b):
 *   peripheral events auto_escalation_sent + token_issued +
 *   token_superseded; NO transitionTo (status stays awaiting_landlord);
 *   case is explicitly saved to persist the clock-restart attributes.
 *   Ratchet advances via the new case_messages row itself; the
 *   counter is derived, not stored.
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
        ?string $tenantStatement = null,
        ?int $actorUserId = null,
        array $attachmentInputs = [],
    ): CaseMessage {
        return DB::transaction(function () use ($case, $tenantStatement, $actorUserId, $attachmentInputs) {
            $isFirstSend = $case->status === CaseStatus::Open;
            $isEscalation = $case->status === CaseStatus::TenantActionRequired;
            $isAutoEscalation = $case->status === CaseStatus::AwaitingLandlord;

            if (! $isFirstSend && ! $isEscalation && ! $isAutoEscalation) {
                throw new LogicException(
                    'SendCaseNotice can only run from Open, TenantActionRequired, or AwaitingLandlord; case is in '.$case->status->value
                );
            }

            // Counter-derived notice number, post-2b:
            // - first send / auto-escalation: current_stage stays meaningful as
            //   the "highest stage reached" — auto-escalation lands at current_stage+1
            //   the same way tenant escalation does, because both advance the
            //   message-derived counter.
            // - tenant escalation: same +1 as today.
            $targetStage = $isFirstSend ? $case->current_stage : $case->current_stage + 1;

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
                'sender_role' => SenderRole::System,
                'stage_at_send' => $targetStage,
                'subject' => null,
                'body_raw' => '',
                'tenant_statement' => $tenantStatement,
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

            // Look up the template via D1 fallback (active stage=N, else
            // active stage=NULL).
            $template = LetterTemplate::forEscalation($targetStage);
            if ($template === null) {
                throw new LogicException(
                    "No active escalation template found for stage {$targetStage}. "
                    .'Seed the letter_templates table (LetterTemplateSeeder) or activate a row.'
                );
            }

            $caseForVars = $case->fresh()->load(['tenant', 'property', 'landlordContact']);
            $rendered = $this->renderer->render(
                $template,
                $this->buildLetterVars($caseForVars, $tenantStatement, $targetStage),
            );

            // The freeze. The rendered subject + body are the evidence:
            // the mailable's send path reads them verbatim and never
            // re-renders. Template id + updated_at snapshot answer
            // "which wording was in force".
            $message->update([
                'letter_template_id' => $template->id,
                'letter_template_updated_at' => $template->updated_at,
                'subject' => $rendered['subject'],
                'body_raw' => $rendered['body'],
            ]);

            // Silence-model clock (re)start. Ball flips to landlord, the
            // silence clock restarts, settings snapshot is refreshed
            // (D4 in-flight guardrail). For first-send and tenant
            // escalation these are persisted by transitionTo's save()
            // below. For auto-escalation we save explicitly since there
            // is no transition.
            $case->ball_with = 'landlord';
            $case->silence_clock_started_at = now();
            $case->silence_settings_snapshot = SilenceClock::snapshotCurrentSettings();

            Mail::to($case->landlordContact->email)->queue(new CaseNotice(
                $caseForVars,
                $message->fresh(),
                $newToken,
            ));

            // Peripheral events. The canonical state-change event (for
            // first-send and tenant-escalation) is written by transitionTo.
            // For auto-escalation there is no transition; auto_escalation_sent
            // is the canonical record of the send in the audit trail and
            // distinguishes system-initiated sends from tenant-initiated
            // (notice_sent + stage_advanced) for evidential purposes.
            if ($isEscalation) {
                $case->events()->create([
                    'event_type' => 'escalation_confirmed_by_tenant',
                    'actor_user_id' => $actorUserId,
                    'actor_label' => 'tenant',
                    'occurred_at' => now(),
                ]);
                $case->events()->create([
                    'event_type' => 'notice_sent',
                    'actor_label' => 'system',
                    'occurred_at' => now(),
                    'meta' => ['stage' => $targetStage, 'message_id' => $message->id],
                ]);
            } elseif ($isAutoEscalation) {
                $case->events()->create([
                    'event_type' => 'auto_escalation_sent',
                    'actor_label' => 'system',
                    'occurred_at' => now(),
                    'meta' => ['stage' => $targetStage, 'message_id' => $message->id],
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
                // No transition — status stays awaiting_landlord. Persist
                // the clock-restart attributes explicitly. Also bump
                // current_stage to mirror the side effect that
                // transitionTo's applyColumnSideEffects performs on the
                // TenantActionRequired → AwaitingLandlord transition.
                $case->current_stage = $targetStage;
                $case->save();
            } else {
                $case->transitionTo(CaseStatus::AwaitingLandlord, [
                    'actor_user_id' => $actorUserId,
                    'actor_label' => 'tenant',
                ]);
            }

            return $message->fresh();
        });
    }

    /**
     * Build the letter-template variables for an escalation send.
     *
     * Whitelist source of truth is LetterTemplateRenderer::WHITELIST.
     * Anything not on that list passes through the renderer as the
     * literal `{{token}}` text, so misspellings are visible.
     *
     * `response_days` reads `escalation.interval_days` from Settings —
     * D4 letter/deadline consistency: the letter's stated deadline
     * matches the source the silence sweep enforces.
     *
     * @return array<string, string|int|null>
     */
    private function buildLetterVars(RepairCase $case, ?string $tenantStatement, int $noticeNumber): array
    {
        return [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordContact->name ?: 'Sir or Madam',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'issue_description' => $tenantStatement,
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

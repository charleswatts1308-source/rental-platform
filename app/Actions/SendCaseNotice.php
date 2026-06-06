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
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use LogicException;

/**
 * Orchestrates the outbound notice flow:
 * supersede the active reply token (if any), mint a new one, compose
 * the outbound case_message, queue the mail to the landlord_contact,
 * write peripheral case_events, and transition the case via
 * transitionTo (which writes the canonical state-change event and
 * applies column side effects like current_stage++).
 *
 * Callable from two case states:
 * - Open (first send): writes token_issued; transitionTo writes
 *   notice_sent as canonical.
 * - TenantActionRequired (escalation): writes
 *   escalation_confirmed_by_tenant, notice_sent, token_issued,
 *   token_superseded; transitionTo writes stage_advanced as
 *   canonical and bumps current_stage.
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

            if (! $isFirstSend && ! $isEscalation) {
                throw new LogicException(
                    'SendCaseNotice can only run from Open or TenantActionRequired; case is in '.$case->status->value
                );
            }

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

            // The template_key column is dual-written alongside the new
            // letter_template_id FK for this phase. Dropping template_key
            // is deferred — Phase 1 ruling 2.
            $message = $case->messages()->create([
                'direction' => MessageDirection::Outbound,
                'sender_role' => SenderRole::System,
                'stage_at_send' => $targetStage,
                'template_key' => $this->templateKeyForStage($targetStage),
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
            // active stage=NULL). With only the generic wake-up seeded in
            // v1, every notice number lands on it.
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

            // The freeze. After this update the rendered subject + body
            // are the evidence: the mailable's send path reads them
            // verbatim and never re-renders. Template id + updated_at
            // snapshot answer "which wording was in force".
            $message->update([
                'letter_template_id' => $template->id,
                'letter_template_updated_at' => $template->updated_at,
                'subject' => $rendered['subject'],
                'body_raw' => $rendered['body'],
            ]);

            // Silence-model clock start (Phase 2a). The letter is now
            // committed evidence; ball flips to landlord; the silence
            // clock starts running. Snapshot the current settings so
            // a mid-flight settings change can't retro-affect this
            // clock (D4 in-flight guardrail).
            //
            // Attributes are set here and persisted by transitionTo's
            // save() below — same pattern as next_stage_eligible_at.
            // Old code paths do not read these columns; zero behaviour
            // change in this phase.
            $case->ball_with = 'landlord';
            $case->silence_clock_started_at = now();
            $case->silence_settings_snapshot = SilenceClock::snapshotCurrentSettings();

            Mail::to($case->landlordContact->email)->queue(new CaseNotice(
                $caseForVars,
                $message->fresh(),
                $newToken,
            ));

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

            $case->next_stage_eligible_at = $this->nextStageEligibleAt($targetStage);
            $case->transitionTo(CaseStatus::AwaitingLandlord, [
                'actor_user_id' => $actorUserId,
                'actor_label' => 'tenant',
            ]);

            return $message->fresh();
        });
    }

    private function templateKeyForStage(int $stage): string
    {
        return match ($stage) {
            1 => 'stage_1_initial_notice',
            2 => 'stage_2_follow_up',
            3 => 'stage_3_formal_warning',
            4 => 'stage_4_pre_action',
            default => throw new LogicException("No template defined for stage {$stage}"),
        };
    }

    /**
     * Build the letter-template variables for an escalation send.
     *
     * Whitelist source of truth is LetterTemplateRenderer::WHITELIST.
     * Anything not on that list passes through the renderer as the
     * literal `{{token}}` text, so misspellings are visible.
     *
     * `response_days` reads `escalation.interval_days` from Settings
     * (Phase 2a swap from the Phase 1 hardcoded 14). The seeded value
     * is 14, matching the prior hardcode, so on-the-wire wording is
     * unchanged. This satisfies D4 letter/deadline consistency: the
     * letter's stated deadline now comes from the same source the new
     * silence scheduler will enforce.
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

    private function nextStageEligibleAt(int $stageJustSent): ?CarbonInterface
    {
        $daysUntilNext = match ($stageJustSent) {
            1, 2 => 14,
            3 => 21,
            4 => null,
            default => null,
        };

        return $daysUntilNext === null ? null : now()->addDays($daysUntilNext);
    }
}

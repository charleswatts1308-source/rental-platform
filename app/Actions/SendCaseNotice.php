<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\ScanStatus;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\MessageAttachment;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use App\Services\ReplyTokenGenerator;
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

            $mailable = new CaseNotice(
                $case->fresh()->load(['tenant', 'property', 'landlordContact', 'category']),
                $message,
                $newToken,
            );

            $message->update([
                'body_raw' => $mailable->render(),
                'subject' => $mailable->envelope()->subject,
            ]);

            Mail::to($case->landlordContact->email)->queue($mailable);

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

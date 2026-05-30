<?php

namespace App\Console\Commands;

use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Console\Command;

/**
 * Drives a repair case through the outbound notice flow up to a target
 * stage (1-4), for local/staging dev.
 *
 * Calls the real SendCaseNotice action (no logic duplicated) and mirrors
 * the production escalation transition (SweepEscalations does
 * transitionTo(TenantActionRequired)), but skips the next_stage_eligible_at
 * time gate so any --stage is reachable immediately (decision 3a).
 *
 * The cycle: from Open, send stage 1; then repeatedly escalate
 * (AwaitingLandlord -> TenantActionRequired) and send, until the highest
 * sent stage equals the target.
 */
class DevLetter extends Command
{
    private const MAX_STAGE = 4;

    /** Safety stop for the drive loop (each stage is two iterations at most). */
    private const MAX_ITERATIONS = 12;

    protected $signature = 'dev:letter
        {--case= : Case id or url_slug (defaults to the latest Open case)}
        {--stage=1 : Target stage to drive the case to (1-4)}
        {--statement= : Tenant statement for the first letter}';

    protected $description = 'Drive a case through outbound notices up to a target stage (local/staging/preprod only)';

    public function handle(SendCaseNotice $notice): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:letter is restricted to the local, staging, and preprod environments.');

            return self::FAILURE;
        }

        $target = (int) $this->option('stage');
        if ($target < 1 || $target > self::MAX_STAGE) {
            $this->error('--stage must be between 1 and '.self::MAX_STAGE.'.');

            return self::FAILURE;
        }

        $case = $this->resolveCase();
        if ($case === null) {
            return self::FAILURE;
        }

        $statement = $this->option('statement') ?? 'Please arrange to inspect and repair the reported issue.';
        $actorUserId = $case->tenant_user_id;
        $lettersSent = 0;

        for ($iter = 0; $iter < self::MAX_ITERATIONS; $iter++) {
            $case->refresh();

            switch ($case->status) {
                case CaseStatus::Open:
                    $message = $notice->execute($case, $statement, $actorUserId);
                    $lettersSent++;
                    $this->line("letter_sent stage={$message->stage_at_send} message_id={$message->id}");
                    break;

                case CaseStatus::AwaitingLandlord:
                    if ($case->current_stage >= $target) {
                        break 2;
                    }
                    $case->transitionTo(CaseStatus::TenantActionRequired);
                    break;

                case CaseStatus::TenantActionRequired:
                    if ($case->current_stage >= $target) {
                        break 2;
                    }
                    $message = $notice->execute($case, null, $actorUserId);
                    $lettersSent++;
                    $this->line("letter_sent stage={$message->stage_at_send} message_id={$message->id}");
                    break;

                default:
                    $this->error(
                        "Case is in {$case->status->value}; dev:letter can only drive "
                        .'Open, AwaitingLandlord, or TenantActionRequired cases.'
                    );

                    return self::FAILURE;
            }
        }

        $case->refresh();
        $this->line(
            "case={$case->url_slug} target_stage={$target} letters_sent={$lettersSent} "
            ."final_status={$case->status->value} current_stage={$case->current_stage}"
        );

        return self::SUCCESS;
    }

    private function resolveCase(): ?RepairCase
    {
        $opt = $this->option('case');

        if ($opt === null) {
            $case = RepairCase::where('status', CaseStatus::Open)->orderByDesc('id')->first();
            if ($case === null) {
                $this->error('No Open case found. Run `php artisan dev:case` first, or pass --case=.');
            }

            return $case;
        }

        $case = is_numeric($opt)
            ? RepairCase::find((int) $opt)
            : RepairCase::where('url_slug', $opt)->first();

        if ($case === null) {
            $this->error("No case found for --case={$opt}.");
        }

        return $case;
    }
}

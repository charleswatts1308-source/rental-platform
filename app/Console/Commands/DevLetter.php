<?php

namespace App\Console\Commands;

use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Console\Command;

/**
 * Drives a freshly-created Open case through its first outbound
 * notice for local/staging dev.
 *
 * Post Phase 3 — escalation is silence-only (D7 resolved); the
 * tenant click into AwaitingLandlord is demolished, so the multi-
 * stage drive loop is also demolished. dev:letter now sends exactly
 * the first notice and stops. Subsequent escalations come from
 * silence:sweep (run in real time or via dev:age-clock +
 * silence:sweep).
 *
 * --stage is retained as a no-op for backward-compat with scripts
 * that pass it.
 */
class DevLetter extends Command
{
    protected $signature = 'dev:letter
        {--case= : Case id or url_slug (defaults to the latest Open case)}
        {--stage=1 : Retained for backward-compat — only stage 1 is reachable here}';

    protected $description = 'Send the first outbound notice for an Open case (local/staging/preprod only)';

    public function handle(SendCaseNotice $notice): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:letter is restricted to the local, staging, and preprod environments.');

            return self::FAILURE;
        }

        $case = $this->resolveCase();
        if ($case === null) {
            return self::FAILURE;
        }

        if ($case->status !== CaseStatus::Open) {
            $this->error(
                "Case is in {$case->status->value}; dev:letter can only drive Open cases. "
                .'For escalation, age the clock and run silence:sweep.'
            );

            return self::FAILURE;
        }

        $message = $notice->execute($case, actorUserId: $case->tenant_user_id);

        $case->refresh();
        $this->line(
            "letter_sent stage={$message->stage_at_send} message_id={$message->id} "
            ."case={$case->url_slug} final_status={$case->status->value} "
            ."current_stage={$case->current_stage}"
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

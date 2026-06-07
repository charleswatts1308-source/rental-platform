<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Console\Command;

/**
 * Subtract days from a case's hold_until to simulate hold expiry —
 * for live-fire testing of silence:sweep's hold-expiry absorption
 * (ResumeFromHold verdict) without waiting real days.
 *
 * Sibling to dev:age-clock; same env allow-list. Only valid on
 * cases currently in OnHold with a non-null hold_until.
 */
class DevAgeHold extends Command
{
    protected $signature = 'dev:age-hold
        {--case= : Case id or url_slug (required)}
        {--days= : Number of days to subtract from hold_until (required, positive int)}';

    protected $description = 'Age a case\'s hold_until by N days (local/staging/preprod only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:age-hold is restricted to the local, staging, and preprod environments.');

            return self::FAILURE;
        }

        $caseOpt = $this->option('case');
        $daysOpt = $this->option('days');

        if ($caseOpt === null || $daysOpt === null) {
            $this->error('Both --case= and --days= are required.');

            return self::FAILURE;
        }

        if (! is_numeric($daysOpt) || (int) $daysOpt < 1) {
            $this->error('--days must be a positive integer.');

            return self::FAILURE;
        }

        $days = (int) $daysOpt;
        $case = is_numeric($caseOpt)
            ? RepairCase::find((int) $caseOpt)
            : RepairCase::where('url_slug', $caseOpt)->first();

        if ($case === null) {
            $this->error("No case found for --case={$caseOpt}.");

            return self::FAILURE;
        }

        if ($case->status !== CaseStatus::OnHold || $case->hold_until === null) {
            $this->error(
                "Case {$case->url_slug} is in status {$case->status->value} with no live hold to age. "
                .'Place the case on hold first.'
            );

            return self::FAILURE;
        }

        $oldHoldUntil = $case->hold_until->copy();
        $newHoldUntil = $oldHoldUntil->copy()->subDays($days);

        $case->hold_until = $newHoldUntil;
        $case->save();

        $this->line("case={$case->url_slug} status={$case->status->value}");
        $this->line("hold_until: {$oldHoldUntil->toDateTimeString()} -> {$newHoldUntil->toDateTimeString()} ({$days} days back)");
        $this->line('Now eligible for ResumeFromHold via silence:sweep.');

        return self::SUCCESS;
    }
}

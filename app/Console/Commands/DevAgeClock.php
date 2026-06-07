<?php

namespace App\Console\Commands;

use App\Models\RepairCase;
use Illuminate\Console\Command;

/**
 * Subtract days from a case's silence_clock_started_at to simulate
 * clock aging — for live-fire testing of silence:sweep without
 * waiting real days or doing phpMyAdmin date surgery.
 *
 * IMPORTANT: this DOES NOT touch silence_settings_snapshot. The
 * snapshot is what was in force when the clock originally started;
 * keeping it intact preserves the D4 in-flight guardrail story
 * (operators can edit a setting after aging the clock and confirm
 * the aged clock still respects the original snapshot).
 *
 * Restricted to local/staging/preprod — same env allow-list as the
 * other dev:* commands.
 */
class DevAgeClock extends Command
{
    protected $signature = 'dev:age-clock
        {--case= : Case id or url_slug (required)}
        {--days= : Number of days to subtract from silence_clock_started_at (required, positive int)}';

    protected $description = 'Age a case\'s silence clock by N days (local/staging/preprod only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:age-clock is restricted to the local, staging, and preprod environments.');

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

        if ($case->silence_clock_started_at === null) {
            $this->error(
                "Case {$case->url_slug} is in status {$case->status->value} with no live silence clock to age. "
                .'Send a letter or land a reply first to start the clock.'
            );

            return self::FAILURE;
        }

        $oldStartedAt = $case->silence_clock_started_at->copy();
        $newStartedAt = $oldStartedAt->copy()->subDays($days);

        // Direct update bypasses the case_events listener and is fine
        // here: no status change, no business event. Pure dev affordance.
        $case->silence_clock_started_at = $newStartedAt;
        $case->save();

        $snapshot = $case->silence_settings_snapshot ?? [];
        $interval = (int) ($snapshot['escalation.interval_days'] ?? 14);
        $silenceDays = (int) floor($newStartedAt->diffInRealSeconds(now(), absolute: false) / 86400);

        $this->line("case={$case->url_slug} status={$case->status->value} ball_with={$case->ball_with}");
        $this->line("clock_started_at: {$oldStartedAt->toDateTimeString()} -> {$newStartedAt->toDateTimeString()} ({$days} days back)");
        $this->line("snapshot.escalation.interval_days: {$interval}");
        $this->line("silence_days now: {$silenceDays}/{$interval} -> ".($silenceDays >= $interval ? 'EXPIRED' : 'not expired'));

        return self::SUCCESS;
    }
}

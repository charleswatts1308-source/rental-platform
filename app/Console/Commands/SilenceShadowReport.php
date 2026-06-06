<?php

namespace App\Console\Commands;

use App\Models\RepairCase;
use App\Models\SilenceShadowLog;
use Illuminate\Console\Command;

/**
 * Human-readable read view of the silence shadow log. Used during
 * Phase 2a to inspect what the silence model would have decided,
 * without needing phpMyAdmin.
 *
 * No UI in this phase — the artisan output is the verification
 * surface.
 */
class SilenceShadowReport extends Command
{
    protected $signature = 'silence:shadow-report
        {--case= : Restrict to a single case (id or url_slug)}
        {--limit=50 : Max rows to show, most recent first}
        {--include-no-action : Include intended_action=no_action rows (default: only show interesting verdicts)}';

    protected $description = 'List recent silence shadow log entries — human-readable verification';

    public function handle(): int
    {
        $query = SilenceShadowLog::query()
            ->with(['case:id,url_slug,status', 'intendedLetterTemplate:id,code'])
            ->orderByDesc('swept_at')
            ->orderByDesc('id');

        if ($caseOpt = $this->option('case')) {
            $case = $this->resolveCase($caseOpt);
            if ($case === null) {
                return self::FAILURE;
            }
            $query->where('case_id', $case->id);
        }

        if (! $this->option('include-no-action')) {
            $query->where('intended_action', '!=', 'no_action');
        }

        $rows = $query->limit((int) $this->option('limit'))->get();

        if ($rows->isEmpty()) {
            $this->line('No matching shadow log rows.');

            return self::SUCCESS;
        }

        $this->table(
            ['swept_at', 'case', 'pretend', 'ball', 'silence_days', 'action', 'esc', 'nudge', 'template', 'reasoning'],
            $rows->map(fn (SilenceShadowLog $row) => [
                $row->swept_at->toDateTimeString(),
                $row->case?->url_slug ?? "id={$row->case_id}",
                $row->is_pretend ? $row->pretend_today?->toDateString() : '-',
                $row->ball_with ?? '-',
                $row->silence_days ?? '-',
                $row->intended_action,
                $row->escalation_counter_value ?? '-',
                $row->nudge_number ?? '-',
                $row->intendedLetterTemplate?->code ?? '-',
                str($row->reasoning)->limit(60),
            ])->all(),
        );

        return self::SUCCESS;
    }

    private function resolveCase(string $opt): ?RepairCase
    {
        $case = is_numeric($opt)
            ? RepairCase::find((int) $opt)
            : RepairCase::where('url_slug', $opt)->first();

        if ($case === null) {
            $this->error("No case found for --case={$opt}.");
        }

        return $case;
    }
}

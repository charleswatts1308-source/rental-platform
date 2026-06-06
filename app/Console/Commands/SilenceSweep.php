<?php

namespace App\Console\Commands;

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use App\Models\SilenceShadowLog;
use App\Services\Silence\SilenceClock;
use App\Services\Silence\SweepVerdict;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Silence-model sweep — Phase 2a SHADOW MODE.
 *
 * For every case in a non-terminal status, asks SilenceClock what it
 * WOULD do and writes a silence_shadow_log row. Sends nothing,
 * transitions nothing. The old SweepEscalations / SweepDormancy /
 * SweepHolds remain fully in charge of real behaviour.
 *
 * `--pretend-today=YYYY-MM-DD` (dev/staging/preprod only) evaluates as
 * if today were that date. Pretend rows are marked is_pretend=true so
 * they are never confused with real-clock data.
 *
 * Time is an injected parameter all the way down — no
 * Carbon::setTestNow, no branching on the pretend flag inside the
 * decision logic. SilenceClock::evaluate receives $now from here, and
 * every test passes its own Carbon.
 */
class SilenceSweep extends Command
{
    protected $signature = 'silence:sweep
        {--pretend-today= : Evaluate as if today were YYYY-MM-DD (local/staging/preprod only)}';

    protected $description = 'Phase 2a SHADOW silence sweep — logs intended actions, sends nothing, transitions nothing';

    public function handle(SilenceClock $clock): int
    {
        $now = $this->resolveNow();
        if ($now === null) {
            return self::FAILURE;
        }

        $isPretend = $this->option('pretend-today') !== null;
        $pretendToday = $isPretend ? $now->toDateString() : null;

        // Sweep every case not in a terminal status. Terminal cases
        // are excluded at the query layer for efficiency; SilenceClock
        // would correctly say no_action for them, but logging that
        // every sweep would just inflate the table.
        $cases = RepairCase::query()
            ->whereNotIn('status', [CaseStatus::Resolved, CaseStatus::Abandoned])
            ->get();

        $counts = [
            'no_action' => 0,
            'send_escalation' => 0,
            'send_nudge' => 0,
            'transition_dormant_intent' => 0,
            'transition_exhausted_intent' => 0,
        ];

        foreach ($cases as $case) {
            $verdict = $clock->evaluate($case, $now);
            $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday);
            $counts[$verdict->intendedAction->value]++;
        }

        $this->line(sprintf(
            'silence:sweep %sevaluated %d case(s) at %s — actions: %s',
            $isPretend ? "(pretend={$pretendToday}) " : '',
            $cases->count(),
            $now->toDateTimeString(),
            implode(', ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($counts), $counts)),
        ));

        return self::SUCCESS;
    }

    /**
     * Resolves the moment-of-evaluation. Real sweeps use Carbon::now().
     * --pretend-today uses 09:00 on the supplied date — a sensible
     * "morning of" reference so the same date input is deterministic
     * across operator invocations.
     */
    private function resolveNow(): ?CarbonInterface
    {
        $pretend = $this->option('pretend-today');

        if ($pretend === null) {
            return Carbon::now();
        }

        if (! app()->environment(['local', 'staging', 'preprod'])) {
            $this->error('--pretend-today is restricted to the local, staging, and preprod environments.');

            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $pretend);
            if ($date === false) {
                throw new InvalidArgumentException('Carbon::createFromFormat returned false');
            }
        } catch (\Exception $e) {
            $this->error("--pretend-today must be a YYYY-MM-DD date; got: {$pretend}");

            return null;
        }

        return $date->startOfDay()->addHours(9);
    }

    private function writeShadowRow(int $caseId, SweepVerdict $verdict, CarbonInterface $now, bool $isPretend, ?string $pretendToday): void
    {
        SilenceShadowLog::create([
            'case_id' => $caseId,
            'swept_at' => $now,
            'ball_with' => $verdict->ballWith?->value,
            'silence_days' => $verdict->silenceDays,
            'intended_action' => $verdict->intendedAction->value,
            'intended_letter_template_id' => $verdict->intendedLetterTemplate?->id,
            'escalation_counter_value' => $verdict->escalationCounterValue,
            'nudge_number' => $verdict->nudgeNumber,
            'reasoning' => $verdict->reasoning,
            'is_pretend' => $isPretend,
            'pretend_today' => $pretendToday,
        ]);
    }
}

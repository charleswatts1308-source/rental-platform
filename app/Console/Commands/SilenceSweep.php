<?php

namespace App\Console\Commands;

use App\Actions\SendCaseNotice;
use App\Enums\BallPosition;
use App\Enums\CaseStatus;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use App\Models\SilenceShadowLog;
use App\Services\LetterTemplateRenderer;
use App\Services\Silence\IntendedAction;
use App\Services\Silence\SilenceClock;
use App\Services\Silence\SweepVerdict;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

/**
 * Silence-model sweep — Phase 2b: LANDLORD-SIDE LIVE, tenant-side
 * shadow, pretend always shadow.
 *
 * For every non-terminal case, asks SilenceClock what it WOULD do and:
 *   - landlord-side send_escalation (real run): EXECUTES the send via
 *     SendCaseNotice. Restarts the clock (inside SendCaseNotice's
 *     transaction), bumps current_stage, writes auto_escalation_sent,
 *     fires the tenant notification per the active-row idiom, logs a
 *     shadow row with executed=true.
 *   - landlord-side exhausted_intent (counter ≥ max_notices): logs
 *     only. The escalation_exhausted state is Phase 4; until then the
 *     case simply stops generating letters. Never re-fires the final
 *     notice (design doc D5 rejected option b).
 *   - tenant-side verdicts (send_nudge, transition_dormant_intent):
 *     stay SHADOW — nudges go live in Phase 3 alongside the tenant
 *     reply UI. A live nudge today would point at a missing action.
 *   - --pretend-today: FORCES FULL SHADOW everywhere. The execution
 *     gate is wrapped in (!$isPretend && ...). Pretend never sends.
 *
 * Per-case exception isolation: a throw inside one case's handling
 * does NOT abort the sweep. The exception is caught, a shadow row is
 * written with executed=false and reasoning that names the exception
 * class + message, and processing continues to the next case.
 *
 * Race / double-fire guards: per-case lockForUpdate inside a
 * transaction, plus withoutOverlapping on the schedule entry. If a
 * concurrent process restarted the clock between verdict-computation
 * and the lock, this run skips with a "superseded by concurrent
 * sweep" reasoning.
 */
class SilenceSweep extends Command
{
    protected $signature = 'silence:sweep
        {--pretend-today= : Evaluate as if today were YYYY-MM-DD (local/staging/preprod only)}';

    protected $description = 'Silence sweep — landlord-side LIVE, tenant-side shadow; --pretend-today forces full shadow';

    public function handle(
        SilenceClock $clock,
        SendCaseNotice $sendCaseNotice,
        LetterTemplateRenderer $renderer,
    ): int {
        $now = $this->resolveNow();
        if ($now === null) {
            return self::FAILURE;
        }

        $isPretend = $this->option('pretend-today') !== null;
        $pretendToday = $isPretend ? $now->toDateString() : null;

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
        $executedCount = 0;
        $exceptionCount = 0;

        foreach ($cases as $case) {
            try {
                $executed = $this->handleCase(
                    $case,
                    $clock,
                    $sendCaseNotice,
                    $renderer,
                    $now,
                    $isPretend,
                    $pretendToday,
                );
                $verdict = $clock->evaluate($case->fresh(), $now);
                $counts[$verdict->intendedAction->value] = ($counts[$verdict->intendedAction->value] ?? 0) + 0;
                if ($executed) {
                    $executedCount++;
                }
            } catch (Throwable $e) {
                // Per-case exception isolation (ruling f). The throw
                // does not abort the sweep — log a shadow row that
                // names the failure and continue to the next case.
                $this->writeExceptionShadowRow($case, $e, $now, $isPretend, $pretendToday);
                $exceptionCount++;
            }
        }

        // Re-tally action counts from the actual shadow rows just
        // written this sweep, to keep the summary honest in the
        // exception-isolated path.
        $writtenThisSweep = SilenceShadowLog::query()
            ->where('swept_at', $now)
            ->where('is_pretend', $isPretend)
            ->get();
        $counts = [
            'no_action' => $writtenThisSweep->where('intended_action', 'no_action')->count(),
            'send_escalation' => $writtenThisSweep->where('intended_action', 'send_escalation')->count(),
            'send_nudge' => $writtenThisSweep->where('intended_action', 'send_nudge')->count(),
            'transition_dormant_intent' => $writtenThisSweep->where('intended_action', 'transition_dormant_intent')->count(),
            'transition_exhausted_intent' => $writtenThisSweep->where('intended_action', 'transition_exhausted_intent')->count(),
        ];

        $this->line(sprintf(
            'silence:sweep %sevaluated %d case(s) at %s — actions: %s; executed=%d; exceptions=%d',
            $isPretend ? "(pretend={$pretendToday}) " : '',
            $cases->count(),
            $now->toDateTimeString(),
            implode(', ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($counts), $counts)),
            $executedCount,
            $exceptionCount,
        ));

        return self::SUCCESS;
    }

    /**
     * Evaluate one case, log a shadow row, and (if eligible) execute
     * the send. Returns true if executed.
     *
     * Race guard: the case is re-read with lockForUpdate inside the
     * transaction. If silence_clock_started_at changed between the
     * pre-lock verdict and the locked re-read, a concurrent sweep
     * acted first — log the verdict but skip the send.
     */
    private function handleCase(
        RepairCase $case,
        SilenceClock $clock,
        SendCaseNotice $sendCaseNotice,
        LetterTemplateRenderer $renderer,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): bool {
        $verdict = $clock->evaluate($case, $now);

        $shouldExecute = !$isPretend
            && $verdict->ballWith === BallPosition::Landlord
            && $verdict->intendedAction === IntendedAction::SendEscalation;

        if (!$shouldExecute) {
            $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: false);
            return false;
        }

        return DB::transaction(function () use (
            $case, $clock, $sendCaseNotice, $renderer, $now, $isPretend, $pretendToday, $verdict
        ): bool {
            $locked = RepairCase::query()
                ->lockForUpdate()
                ->find($case->id);

            if ($locked === null) {
                $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: false);
                return false;
            }

            $clockBefore = $case->silence_clock_started_at;
            $clockNow = $locked->silence_clock_started_at;

            if ($clockBefore === null || $clockNow === null || ! $clockNow->equalTo($clockBefore)) {
                $supersededVerdict = new SweepVerdict(
                    intendedAction: IntendedAction::NoAction,
                    ballWith: $verdict->ballWith,
                    silenceDays: $verdict->silenceDays,
                    intendedLetterTemplate: null,
                    escalationCounterValue: $verdict->escalationCounterValue,
                    nudgeNumber: null,
                    reasoning: 'superseded by concurrent sweep — clock restarted between verdict and lock',
                );
                $this->writeShadowRow($case->id, $supersededVerdict, $now, $isPretend, $pretendToday, executed: false);
                return false;
            }

            $sendCaseNotice->execute($locked);

            $this->dispatchAutoEscalationTenantNotice($locked->fresh(), $verdict, $renderer);

            $this->writeShadowRow($case->id, $verdict, $now, $isPretend, $pretendToday, executed: true);

            return true;
        });
    }

    /**
     * Active-row idiom: if an active tenant_notification template
     * coded auto_escalation_tenant_notice exists, render and queue
     * the notification. Otherwise skip silently. The template row
     * is the on/off switch.
     *
     * This mail goes to the tenant. It is NOT persisted as a
     * case_messages row — the counter predicate (SilenceClock::
     * escalationCounter) depends on that.
     */
    private function dispatchAutoEscalationTenantNotice(
        RepairCase $case,
        SweepVerdict $verdict,
        LetterTemplateRenderer $renderer,
    ): void {
        $template = LetterTemplate::query()
            ->where('type', 'tenant_notification')
            ->where('code', 'auto_escalation_tenant_notice')
            ->where('active', true)
            ->first();

        if ($template === null) {
            return;
        }

        $case->loadMissing(['tenant', 'property', 'landlordContact']);

        $rendered = $renderer->render($template, [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $case->landlordContact->name ?: 'your landlord',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'notice_number' => $case->current_stage,
            'response_days' => $verdict->silenceDays
                ?? (int) ($case->silence_settings_snapshot['escalation.interval_days'] ?? 14),
        ]);

        Mail::to($case->tenant->email)->queue(new AutoEscalationTenantNotice(
            renderedSubject: $rendered['subject'],
            renderedBody: $rendered['body'],
        ));
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
        } catch (\Exception) {
            $this->error("--pretend-today must be a YYYY-MM-DD date; got: {$pretend}");

            return null;
        }

        return $date->startOfDay()->addHours(9);
    }

    private function writeShadowRow(
        int $caseId,
        SweepVerdict $verdict,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
        bool $executed,
    ): void {
        SilenceShadowLog::create([
            'case_id' => $caseId,
            'swept_at' => $now,
            'ball_with' => $verdict->ballWith?->value,
            'silence_days' => $verdict->silenceDays,
            'intended_action' => $verdict->intendedAction->value,
            'executed' => $executed,
            'intended_letter_template_id' => $verdict->intendedLetterTemplate?->id,
            'escalation_counter_value' => $verdict->escalationCounterValue,
            'nudge_number' => $verdict->nudgeNumber,
            'reasoning' => $verdict->reasoning,
            'is_pretend' => $isPretend,
            'pretend_today' => $pretendToday,
        ]);
    }

    private function writeExceptionShadowRow(
        RepairCase $case,
        Throwable $e,
        CarbonInterface $now,
        bool $isPretend,
        ?string $pretendToday,
    ): void {
        $exClass = get_class($e);
        $exMessage = $e->getMessage();
        $reasoning = "exception during sweep: {$exClass}: {$exMessage}";
        if (strlen($reasoning) > 255) {
            $reasoning = substr($reasoning, 0, 252).'...';
        }

        SilenceShadowLog::create([
            'case_id' => $case->id,
            'swept_at' => $now,
            'ball_with' => null,
            'silence_days' => null,
            'intended_action' => 'no_action',
            'executed' => false,
            'intended_letter_template_id' => null,
            'escalation_counter_value' => null,
            'nudge_number' => null,
            'reasoning' => $reasoning,
            'is_pretend' => $isPretend,
            'pretend_today' => $pretendToday,
        ]);
    }
}

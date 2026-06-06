<?php

namespace App\Services\Silence;

use App\Enums\BallPosition;
use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use App\Models\Setting;
use Carbon\CarbonInterface;

/**
 * Pure silence-model decision logic.
 *
 * Nothing in this class mutates state, sends mail, or persists rows —
 * it computes, and the silence:sweep command does the persistence (a
 * shadow log row in Phase 2a; real action in 2b).
 *
 * Time is an injected parameter throughout. `now` is whatever the
 * caller hands in — `Carbon::now()` for real sweeps, the
 * --pretend-today value for time-travel sweeps, a fixed Carbon for
 * tests. This means the class has zero conditional branching on the
 * pretend flag.
 *
 * Turn detection is the pure message-direction rule
 * (silence-phase-2a ruling a): whoever did NOT send the latest
 * case_messages row holds the ball. Status acts as a no-clock veto
 * for Open/OnHold/Resolved/Abandoned/Dormant.
 *
 * 2B-IMPLEMENTER NOTE (silence-phase-2a ruling d):
 *     The escalation counter in `escalationCounter()` below depends
 *     on the invariant: outbound system rows on case_messages with
 *     non-null stage_at_send ARE escalation letters, exclusively.
 *     Tenant nudges, when wired (Phase 2b), must therefore NOT be
 *     persisted as case_messages rows. Nudges are mail-only or live
 *     on a separate non-evidential table. D2 backs this: nudges are
 *     never part of the landlord-facing evidential record.
 *     Breaking this invariant will inflate the escalation counter
 *     and cause shadow-correct sends to misfire.
 */
class SilenceClock
{
    /**
     * Settings keys snapshotted onto the case at clock start.
     * Frozen list — adding/removing a key requires a thought-out
     * migration for in-flight cases.
     */
    public const SNAPSHOT_KEYS = [
        'escalation.interval_days',
        'escalation.max_notices',
        'nudge.first_days',
        'nudge.second_days',
        'nudge.dormancy_days',
    ];

    /** Statuses where no silence clock runs. */
    private const NO_CLOCK_STATUSES = [
        CaseStatus::Open,
        CaseStatus::OnHold,
        CaseStatus::Resolved,
        CaseStatus::Abandoned,
        CaseStatus::Dormant,
    ];

    /**
     * Read the five clock-relevant settings live. Used at clock-start
     * (SendCaseNotice + HandleInboundReply) to freeze the snapshot
     * onto the case.
     *
     * Defaults match the seeded SettingSeeder values so a missing row
     * never silently changes behaviour. All values cast to int — the
     * column is string but every consumer wants an int.
     *
     * @return array<string, int>
     */
    public static function snapshotCurrentSettings(): array
    {
        return [
            'escalation.interval_days' => (int) Setting::get('escalation.interval_days', 14),
            'escalation.max_notices' => (int) Setting::get('escalation.max_notices', 4),
            'nudge.first_days' => (int) Setting::get('nudge.first_days', 10),
            'nudge.second_days' => (int) Setting::get('nudge.second_days', 20),
            'nudge.dormancy_days' => (int) Setting::get('nudge.dormancy_days', 30),
        ];
    }

    /**
     * Resolve ball position via the pure message-direction rule.
     *
     * Returns null when the case is in a no-clock status, or when no
     * messages exist yet (an Open case before first send).
     */
    public function ballFor(RepairCase $case): ?BallPosition
    {
        if (in_array($case->status, self::NO_CLOCK_STATUSES, true)) {
            return null;
        }

        $latest = $case->messages()
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return null;
        }

        return $latest->direction === MessageDirection::Outbound
            ? BallPosition::Landlord
            : BallPosition::Tenant;
    }

    /**
     * Count the escalation letters already sent for a case.
     *
     * Predicate is "outbound system messages with a non-null
     * stage_at_send". This counts ladder letters today (post-Phase-1
     * with letter_template_id stamped; legacy template_key-only rows
     * are equally caught by the stage_at_send filter), and it
     * naturally excludes the future Phase 4 exhaustion_landlord row
     * (which will have stage_at_send=NULL) plus any future
     * sender_role=tenant outbound (Phase 3).
     *
     * Counter is a derived value, never reset — D3.
     */
    public function escalationCounter(RepairCase $case): int
    {
        return $case->messages()
            ->where('direction', MessageDirection::Outbound)
            ->where('sender_role', SenderRole::System)
            ->whereNotNull('stage_at_send')
            ->count();
    }

    /**
     * Evaluate a case at a given moment. Returns the decision the
     * silence model would have taken, packaged as a SweepVerdict.
     *
     * Pure function: reads case + snapshot + case_messages, returns a
     * verdict. No DB writes, no mail, no transitions.
     */
    public function evaluate(RepairCase $case, CarbonInterface $now): SweepVerdict
    {
        $ball = $this->ballFor($case);

        if ($ball === null) {
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: null,
                silenceDays: null,
                intendedLetterTemplate: null,
                escalationCounterValue: null,
                nudgeNumber: null,
                reasoning: 'no clock — status='.$case->status->value,
            );
        }

        // Ball-bearing status without a clock-start is a wiring gap
        // (case predates Phase 2a or both touchpoints failed to fire).
        // Log it as no_action so the operator can see it in the report.
        if ($case->silence_clock_started_at === null || empty($case->silence_settings_snapshot)) {
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: $ball,
                silenceDays: null,
                intendedLetterTemplate: null,
                escalationCounterValue: $ball === BallPosition::Landlord
                    ? $this->escalationCounter($case)
                    : null,
                nudgeNumber: null,
                reasoning: 'no clock-start data on case (predates Phase 2a or wiring gap)',
            );
        }

        $silenceDays = (int) floor($case->silence_clock_started_at->diffInRealSeconds($now, absolute: false) / 86400);
        $snapshot = $case->silence_settings_snapshot;

        return $ball === BallPosition::Landlord
            ? $this->landlordSideVerdict($case, $silenceDays, $snapshot)
            : $this->tenantSideVerdict($case, $silenceDays, $snapshot);
    }

    /**
     * @param  array<string, int>  $snapshot
     */
    private function landlordSideVerdict(RepairCase $case, int $silenceDays, array $snapshot): SweepVerdict
    {
        $interval = (int) ($snapshot['escalation.interval_days'] ?? 14);
        $maxNotices = (int) ($snapshot['escalation.max_notices'] ?? 4);
        $counter = $this->escalationCounter($case);

        if ($silenceDays < $interval) {
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: BallPosition::Landlord,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: $counter,
                nudgeNumber: null,
                reasoning: "clock not expired ({$silenceDays}/{$interval} days); ball=landlord; counter={$counter}",
            );
        }

        if ($counter >= $maxNotices) {
            return new SweepVerdict(
                intendedAction: IntendedAction::TransitionExhaustedIntent,
                ballWith: BallPosition::Landlord,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: $counter,
                nudgeNumber: null,
                reasoning: "ladder exhausted (counter={$counter} >= max={$maxNotices}); would transition to escalation_exhausted (Phase 4)",
            );
        }

        $nextNoticeNumber = $counter + 1;
        $template = LetterTemplate::forEscalation($nextNoticeNumber);

        if ($template === null) {
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: BallPosition::Landlord,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: $counter,
                nudgeNumber: null,
                reasoning: "WOULD send escalation N={$nextNoticeNumber} but no active escalation template found — misconfiguration",
            );
        }

        $resolutionNote = $template->stage === $nextNoticeNumber
            ? "stage={$nextNoticeNumber} match"
            : 'NULL fallback';

        return new SweepVerdict(
            intendedAction: IntendedAction::SendEscalation,
            ballWith: BallPosition::Landlord,
            silenceDays: $silenceDays,
            intendedLetterTemplate: $template,
            escalationCounterValue: $counter,
            nudgeNumber: null,
            reasoning: "clock expired ({$silenceDays}/{$interval} days); ball=landlord; counter={$counter}; next N={$nextNoticeNumber}; template id={$template->id} ({$resolutionNote})",
        );
    }

    /**
     * @param  array<string, int>  $snapshot
     */
    private function tenantSideVerdict(RepairCase $case, int $silenceDays, array $snapshot): SweepVerdict
    {
        $first = (int) ($snapshot['nudge.first_days'] ?? 10);
        $second = (int) ($snapshot['nudge.second_days'] ?? 20);
        $dormancy = (int) ($snapshot['nudge.dormancy_days'] ?? 30);

        // Stateless derivation per ruling c: nudge number is a function
        // of silence_days vs the three snapshot thresholds. The shadow
        // log row records the derived number; nothing is stored on the
        // case.
        if ($silenceDays >= $dormancy) {
            return new SweepVerdict(
                intendedAction: IntendedAction::TransitionDormantIntent,
                ballWith: BallPosition::Tenant,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: null,
                nudgeNumber: null,
                reasoning: "tenant silence {$silenceDays} >= dormancy={$dormancy} days; would transition to dormant",
            );
        }

        if ($silenceDays >= $second) {
            return $this->buildNudgeVerdict($silenceDays, nudgeNumber: 2, reasoning: "second-nudge threshold ({$silenceDays} >= {$second} days)");
        }

        if ($silenceDays >= $first) {
            return $this->buildNudgeVerdict($silenceDays, nudgeNumber: 1, reasoning: "first-nudge threshold ({$silenceDays} >= {$first} days)");
        }

        return new SweepVerdict(
            intendedAction: IntendedAction::NoAction,
            ballWith: BallPosition::Tenant,
            silenceDays: $silenceDays,
            intendedLetterTemplate: null,
            escalationCounterValue: null,
            nudgeNumber: null,
            reasoning: "tenant silence below first-nudge threshold ({$silenceDays}/{$first} days)",
        );
    }

    private function buildNudgeVerdict(int $silenceDays, int $nudgeNumber, string $reasoning): SweepVerdict
    {
        $template = LetterTemplate::firstActiveOfType('tenant_nudge');

        return new SweepVerdict(
            intendedAction: IntendedAction::SendNudge,
            ballWith: BallPosition::Tenant,
            silenceDays: $silenceDays,
            intendedLetterTemplate: $template,
            escalationCounterValue: null,
            nudgeNumber: $nudgeNumber,
            reasoning: $template === null
                ? "{$reasoning}; WOULD send nudge {$nudgeNumber} but no active tenant_nudge template — misconfiguration"
                : "{$reasoning}; ball=tenant; nudge={$nudgeNumber}; template id={$template->id}",
        );
    }
}

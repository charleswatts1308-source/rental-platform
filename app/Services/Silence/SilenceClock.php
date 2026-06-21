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
        // D14 — terminal: the clock stops permanently at ladder exhaustion.
        // No further automatic letters (the D3 ratchet at counter >= max
        // guarantees this even if allow-reply later restarts the clock).
        CaseStatus::EscalationExhausted,
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
     * B2 — global "apply interval changes to in-flight cases" flag.
     * Ships off (in-flight cases stay on their clock-start snapshot).
     * Deliberately NOT a SNAPSHOT_KEY: read live at evaluate time, never
     * frozen onto a case.
     */
    public static function applyInflight(): bool
    {
        return (bool) (int) Setting::get('escalation.apply_inflight', 0);
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
        // Hold-expiry detection precedes the no-clock veto: OnHold is a
        // no-silence-clock state, but its OWN deadline (hold_until) is
        // what the sweep's hold-expiry absorption (Phase 3) hangs on.
        if ($case->status === CaseStatus::OnHold && $case->hold_until !== null && $case->hold_until->lessThanOrEqualTo($now)) {
            return new SweepVerdict(
                intendedAction: IntendedAction::ResumeFromHold,
                ballWith: null,
                silenceDays: null,
                intendedLetterTemplate: null,
                escalationCounterValue: null,
                nudgeNumber: null,
                reasoning: "hold expired (hold_until={$case->hold_until->toDateString()}); transitions to awaiting_landlord",
            );
        }

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

        // B2 — thresholds source. Default (flag off) reads the per-case
        // snapshot frozen at clock start, so an interval change does NOT reach
        // an in-flight case: each case runs its whole life under one set of
        // intervals (clean observability). When escalation.apply_inflight is
        // on, read current settings instead, so a changed interval reaches
        // in-flight cases at the next sweep.
        $snapshot = self::applyInflight()
            ? self::snapshotCurrentSettings()
            : $case->silence_settings_snapshot;

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

        // D15 — engagement-gated escalation. The clock has expired and an
        // escalation is due, but if the landlord has ever engaged on this
        // case we do NOT auto-send: withhold and surface the prepared
        // notice for tenant authorisation (ruling 2 — the case stays
        // landlord-ball). Never-engaged falls through to the unchanged
        // SendEscalation path below.
        if ($case->landlord_engaged) {
            return $this->heldEscalationVerdict($case, $silenceDays, $snapshot, $counter);
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
     * Tenant-side verdict — the next unwalked rung of the ladder
     * (D2 explained-recoverable-sequence promise).
     *
     * The verdict is NOT "what threshold has the silence reached" but
     * "what step is owed next, given what's been sent." TransitionDormantIntent
     * only fires when the full nudge ladder has been walked (count >= 2);
     * if a sweep gap drops a case into silence_days >= dormancy_days
     * without having walked the rungs, the verdict is the next unsent
     * nudge — one per sweep, clock untouched.
     *
     * Normal daily cadence: identical to the threshold-reached reading
     * (silence reaches X, count is X-1, next is X). Gap scenarios (sweep
     * disabled, settings change, hold expiry) are the case this fix
     * actually treats — dormancy never lands on an unwarned case.
     *
     * @param  array<string, int>  $snapshot
     */
    private function tenantSideVerdict(RepairCase $case, int $silenceDays, array $snapshot): SweepVerdict
    {
        $first = (int) ($snapshot['nudge.first_days'] ?? 10);
        $second = (int) ($snapshot['nudge.second_days'] ?? 20);
        $dormancy = (int) ($snapshot['nudge.dormancy_days'] ?? 30);

        if ($silenceDays < $first) {
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

        $nudgesSent = $this->nudgesSentSinceClockStart($case);

        // Dormancy fires only when both nudges have been sent AND silence
        // has reached the dormancy threshold. Otherwise the verdict walks
        // the ladder.
        if ($silenceDays >= $dormancy && $nudgesSent >= 2) {
            return new SweepVerdict(
                intendedAction: IntendedAction::TransitionDormantIntent,
                ballWith: BallPosition::Tenant,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: null,
                nudgeNumber: null,
                reasoning: "tenant silence {$silenceDays} >= dormancy={$dormancy} days; nudge ladder walked (count={$nudgesSent}); would transition to dormant",
            );
        }

        $nextNudgeNumber = $nudgesSent + 1;

        if ($nextNudgeNumber > 2) {
            // All nudges sent but silence < dormancy threshold — wait.
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: BallPosition::Tenant,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: null,
                nudgeNumber: null,
                reasoning: "tenant silence {$silenceDays} days; nudge ladder walked (count={$nudgesSent}); awaiting dormancy threshold ({$dormancy})",
            );
        }

        // Each rung has its own minimum-silence threshold; if silence
        // hasn't yet reached the threshold for the next unsent nudge,
        // wait. This is the daily-cadence "between rungs" no-op.
        $requiredSilence = $nextNudgeNumber === 1 ? $first : $second;
        if ($silenceDays < $requiredSilence) {
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: BallPosition::Tenant,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: null,
                nudgeNumber: null,
                reasoning: "tenant silence {$silenceDays} days; next nudge {$nextNudgeNumber} needs silence >= {$requiredSilence}",
            );
        }

        return $this->buildNudgeVerdict(
            $silenceDays,
            nudgeNumber: $nextNudgeNumber,
            reasoning: "next unsent nudge {$nextNudgeNumber} (count={$nudgesSent}); silence {$silenceDays} >= threshold {$requiredSilence}",
        );
    }

    /**
     * Count `nudge_sent` events on the case since the current silence
     * clock started. Resets to 0 implicitly on any clock restart (the
     * old events become older than silence_clock_started_at and are
     * excluded from the count).
     */
    private function nudgesSentSinceClockStart(RepairCase $case): int
    {
        if ($case->silence_clock_started_at === null) {
            return 0;
        }

        return $case->events()
            ->where('event_type', 'nudge_sent')
            ->where('occurred_at', '>=', $case->silence_clock_started_at)
            ->count();
    }

    /**
     * D15 — held-escalation verdict for an engaged-then-quiet landlord.
     *
     * Reached only from landlordSideVerdict when the escalation clock has
     * expired (silenceDays >= interval), the counter is below max, AND the
     * landlord has engaged. The escalation is WITHHELD; instead the case
     * walks an authorise-nudge ladder that mirrors the tenant-side nudge
     * ladder (D2's explained-recoverable-sequence promise), but:
     *   - it is landlord-ball throughout (ruling 2) — ballWith=Landlord;
     *   - it counts `authorisation_nudge_sent` events, not `nudge_sent`,
     *     so it never collides with the tenant-side ladder;
     *   - if the tenant never authorises, it transitions
     *     awaiting_landlord -> dormant (the new D15 edge), not the
     *     tenant-side awaiting_tenant_review -> dormant edge. D11 revival
     *     applies as for any dormant case.
     *
     * The nudge cadence reuses the existing nudge.* settings (D0.8 — no
     * new settings rows). Because this branch is entered only at
     * silenceDays >= interval, the first authorise-nudge fires as soon as
     * the case is held (the >= first-day threshold is already satisfied);
     * the second follows nudge.second_days; dormancy at nudge.dormancy_days
     * once both authorise-nudges have gone out.
     *
     * @param  array<string, int>  $snapshot
     */
    private function heldEscalationVerdict(RepairCase $case, int $silenceDays, array $snapshot, int $counter): SweepVerdict
    {
        $first = (int) ($snapshot['nudge.first_days'] ?? 10);
        $second = (int) ($snapshot['nudge.second_days'] ?? 20);
        $dormancy = (int) ($snapshot['nudge.dormancy_days'] ?? 30);

        $nudgesSent = $this->authorisationNudgesSentSinceClockStart($case);

        // Unauthorised tail: both authorise-nudges sent AND silence past
        // the dormancy threshold → dormant (awaiting_landlord -> dormant).
        if ($silenceDays >= $dormancy && $nudgesSent >= 2) {
            return new SweepVerdict(
                intendedAction: IntendedAction::TransitionDormantIntent,
                ballWith: BallPosition::Landlord,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: $counter,
                nudgeNumber: null,
                reasoning: "engaged-quiet held escalation unauthorised; silence {$silenceDays} >= dormancy={$dormancy}; authorise-nudge ladder walked (count={$nudgesSent}); would transition awaiting_landlord -> dormant",
            );
        }

        $nextNudgeNumber = $nudgesSent + 1;

        if ($nextNudgeNumber > 2) {
            // Both authorise-nudges sent but silence < dormancy — wait.
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: BallPosition::Landlord,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: $counter,
                nudgeNumber: null,
                reasoning: "engaged-quiet; escalation withheld; authorise-nudge ladder walked (count={$nudgesSent}); awaiting dormancy threshold ({$dormancy})",
            );
        }

        $requiredSilence = $nextNudgeNumber === 1 ? $first : $second;
        if ($silenceDays < $requiredSilence) {
            return new SweepVerdict(
                intendedAction: IntendedAction::NoAction,
                ballWith: BallPosition::Landlord,
                silenceDays: $silenceDays,
                intendedLetterTemplate: null,
                escalationCounterValue: $counter,
                nudgeNumber: null,
                reasoning: "engaged-quiet; escalation withheld; next authorise-nudge {$nextNudgeNumber} needs silence >= {$requiredSilence} ({$silenceDays} so far)",
            );
        }

        // The withheld notice (for shadow-log identification + so the
        // authorise screen can render it). Counter is NOT incremented —
        // the D3 ratchet advances only when the tenant authorises and the
        // real send fires through SendCaseNotice.
        $template = LetterTemplate::forEscalation($counter + 1);

        return new SweepVerdict(
            intendedAction: IntendedAction::SendAuthorisationNudge,
            ballWith: BallPosition::Landlord,
            silenceDays: $silenceDays,
            intendedLetterTemplate: $template,
            escalationCounterValue: $counter,
            nudgeNumber: $nextNudgeNumber,
            reasoning: "engaged-quiet; escalation withheld for tenant authorisation; authorise-nudge {$nextNudgeNumber} (count={$nudgesSent}); silence {$silenceDays} >= {$requiredSilence}; withheld notice N=".($counter + 1),
        );
    }

    /**
     * Count `authorisation_nudge_sent` events since the current silence
     * clock started — the held-escalation analogue of
     * nudgesSentSinceClockStart. Resets implicitly on any clock restart
     * (e.g. when the tenant authorises and the escalation send restarts
     * the landlord clock).
     */
    private function authorisationNudgesSentSinceClockStart(RepairCase $case): int
    {
        if ($case->silence_clock_started_at === null) {
            return 0;
        }

        return $case->events()
            ->where('event_type', 'authorisation_nudge_sent')
            ->where('occurred_at', '>=', $case->silence_clock_started_at)
            ->count();
    }

    /**
     * D15 — the derived "tenant authorisation required" condition, used by
     * the case page (to show the authorise prompt) and the policy (to gate
     * the authorise action). Single source of truth: the same facts the
     * sweep's held-escalation branch keys off.
     *
     * True when: status awaiting_landlord, landlord engaged, ball with the
     * landlord, clock started, the escalation clock has expired, and the
     * ladder is not exhausted. NOT a stored state (D0.3) — recomputed each
     * read. `now` is injected (no Carbon::now() in the decision path).
     */
    public function authorisationPending(RepairCase $case, CarbonInterface $now): bool
    {
        if ($case->status !== CaseStatus::AwaitingLandlord || ! $case->landlord_engaged) {
            return false;
        }

        if ($this->ballFor($case) !== BallPosition::Landlord) {
            return false;
        }

        if ($case->silence_clock_started_at === null || empty($case->silence_settings_snapshot)) {
            return false;
        }

        $snapshot = $case->silence_settings_snapshot;
        $interval = (int) ($snapshot['escalation.interval_days'] ?? 14);
        $maxNotices = (int) ($snapshot['escalation.max_notices'] ?? 4);

        $silenceDays = (int) floor($case->silence_clock_started_at->diffInRealSeconds($now, absolute: false) / 86400);
        if ($silenceDays < $interval) {
            return false;
        }

        return $this->escalationCounter($case) < $maxNotices;
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

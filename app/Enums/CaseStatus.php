<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Open = 'open';
    case AwaitingLandlord = 'awaiting_landlord';
    case AwaitingTenantReview = 'awaiting_tenant_review';
    case OnHold = 'on_hold';
    case Resolved = 'resolved';
    case Abandoned = 'abandoned';
    case Dormant = 'dormant';
    // D14 (Phase 4) — terminal state reached when a never-engaged landlord
    // ignores the full escalation ladder (counter >= max_notices). The clock
    // stops: no further automatic escalation letters. A reply from either
    // party revives the case (D14 allow-reply); the tenant may frame the
    // outcome with a label-only stance (see App\Enums\ExhaustedStance).
    case EscalationExhausted = 'escalation_exhausted';

    /**
     * Snag #47 — status classification is EXHAUSTIVE and lives here.
     *
     * Both predicates below use `match` with NO `default` arm. Adding a
     * case to this enum therefore throws \UnhandledMatchError until it is
     * explicitly classified, and StatusClassificationTest walks every case
     * so the failure lands in the suite rather than in a live sweep.
     *
     * This replaces two independent DENY-lists (SilenceSweep's whereNotIn
     * and SilenceClock's NO_CLOCK_STATUSES). Those defaulted a new status
     * INTO being swept and INTO having a clock — so a forgotten entry
     * produced an actively escalating case, not an inert one. On a product
     * whose output is an evidential record, the safe default has to be
     * "do nothing until told".
     */

    /**
     * Does the silence sweep consider this case at all?
     *
     * False for terminal and sweep-inert states. Mirrors exactly the four
     * statuses previously named in SilenceSweep's whereNotIn.
     */
    public function isSweepable(): bool
    {
        return match ($this) {
            self::Open,
            self::AwaitingLandlord,
            self::AwaitingTenantReview,
            self::OnHold => true,

            self::Resolved,
            self::Abandoned,
            self::Dormant,
            // D14 — terminal, sweep-inert at every stance value.
            self::EscalationExhausted => false,
        };
    }

    /**
     * Does a silence clock run in this status?
     *
     * Distinct from isSweepable(): OnHold IS swept (the sweep releases an
     * expired hold) but runs NO clock while held. Mirrors exactly the
     * statuses previously named in SilenceClock::NO_CLOCK_STATUSES.
     */
    public function hasSilenceClock(): bool
    {
        return match ($this) {
            self::AwaitingLandlord,
            self::AwaitingTenantReview => true,

            self::Open,
            self::OnHold,
            self::Resolved,
            self::Abandoned,
            self::Dormant,
            // D14 — terminal: the clock stops permanently at ladder
            // exhaustion. No further automatic escalation letters.
            self::EscalationExhausted => false,
        };
    }

    /**
     * Fully closed: shut for good, no transitions out (RepairCase::TRANSITIONS
     * maps both to []). escalation_exhausted is NOT closed — it is revivable
     * and closable (D14).
     *
     * Display-only; never gates a transition (transitionTo owns that).
     */
    public function isClosedStatus(): bool
    {
        return match ($this) {
            self::Resolved,
            self::Abandoned => true,

            self::Open,
            self::AwaitingLandlord,
            self::AwaitingTenantReview,
            self::OnHold,
            self::Dormant,
            self::EscalationExhausted => false,
        };
    }

    /**
     * The statuses the sweep should load. An ALLOW-list derived from
     * isSweepable(), so a new status is excluded until classified.
     *
     * @return list<self>
     */
    public static function sweepable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status->isSweepable(),
        ));
    }
}

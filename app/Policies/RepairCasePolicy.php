<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use App\Models\Setting;
use App\Models\User;
use App\Services\Silence\SilenceClock;

/**
 * Authorisation for tenant access to repair cases. Ownership is the
 * baseline (cases.tenant_user_id === user.id); each action method
 * additionally constrains the source statuses from which the action
 * is valid.
 *
 * Post Phase 3:
 *   - sendNext + reEngage demolished (D7 + Option A).
 *   - reply added (D8) — availability matches the D8 table; the
 *     dormancy revival window (D11) is checked here for Dormant
 *     cases (read live, not snapshotted, per D0.9).
 */
class RepairCasePolicy
{
    public function __construct(
        private SilenceClock $silenceClock,
    ) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, RepairCase $case): bool
    {
        return $case->tenant_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * D8 — tenant reply availability:
     *   AwaitingTenantReview: yes (the original half-duplex snag)
     *   AwaitingLandlord:     yes (add-info; restarts clock per D6)
     *   OnHold:               yes (reply IS the resume action)
     *   Dormant:              yes within dormancy.revival_days
     *   Resolved / Abandoned: never
     */
    public function reply(User $user, RepairCase $case): bool
    {
        if (! $this->ownsCase($user, $case)) {
            return false;
        }

        return match ($case->status) {
            CaseStatus::AwaitingTenantReview,
            CaseStatus::AwaitingLandlord,
            CaseStatus::OnHold => true,
            CaseStatus::Dormant => $this->dormantRevivalAvailable($case),
            // D14 — allow-reply revives an exhausted case to awaiting_landlord.
            // No revival window (unlike dormancy): a reply revives whenever
            // it lands.
            CaseStatus::EscalationExhausted => true,
            default => false,
        };
    }

    /**
     * D14 — set the cosmetic exhausted_stance label. Available only on an
     * escalation_exhausted case; ownership is the baseline. The action sets
     * a display label and nothing mechanical (see CaseController::setStance).
     */
    public function setStance(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && $case->status === CaseStatus::EscalationExhausted;
    }

    /**
     * D15 — tenant authorises a withheld escalation notice. Available only
     * when the case is in the engaged-then-quiet held condition, derived
     * live from SilenceClock (the same facts the sweep's held-escalation
     * branch keys off). Ownership is the baseline.
     */
    public function authoriseEscalation(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && $this->silenceClock->authorisationPending($case, now());
    }

    public function hold(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && in_array($case->status, [
                CaseStatus::AwaitingTenantReview,
                CaseStatus::AwaitingLandlord,
            ], true);
    }

    public function resolve(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && in_array($case->status, [
                CaseStatus::AwaitingLandlord,
                CaseStatus::AwaitingTenantReview,
                CaseStatus::OnHold,
                CaseStatus::Dormant,
                // D14 — the tenant may still deliberately close an exhausted
                // case (design doc D5).
                CaseStatus::EscalationExhausted,
            ], true);
    }

    public function abandon(User $user, RepairCase $case): bool
    {
        return $this->resolve($user, $case);
    }

    private function dormantRevivalAvailable(RepairCase $case): bool
    {
        if ($case->dormant_at === null) {
            return false;
        }

        $revivalDays = (int) Setting::get('dormancy.revival_days', 90);

        return $case->dormant_at->copy()->addDays($revivalDays)->isFuture();
    }

    private function ownsCase(User $user, RepairCase $case): bool
    {
        return $case->tenant_user_id === $user->id;
    }
}

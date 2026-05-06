<?php

namespace App\Policies;

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use App\Models\User;

/**
 * Authorisation for tenant access to repair cases. Ownership is the
 * baseline (cases.tenant_user_id === user.id); each action method
 * additionally constrains the source statuses from which the action
 * is valid, mirroring the rows in the design doc's state transition
 * table. This double-gate (ownership + state) is what backs the
 * Bootstrap action panel's @can checks and blocks invalid POSTs even
 * if the panel UI is bypassed.
 *
 * Admin-only transitions (e.g. dormant → abandoned) are intentionally
 * not modelled here — they belong to a future admin policy and would
 * fail this policy's tenant-only checks today.
 */
class RepairCasePolicy
{
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

    public function sendNext(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && $case->status === CaseStatus::TenantActionRequired;
    }

    public function hold(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && in_array($case->status, [
                CaseStatus::AwaitingTenantReview,
                CaseStatus::TenantActionRequired,
            ], true);
    }

    public function resolve(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && in_array($case->status, [
                CaseStatus::AwaitingLandlord,
                CaseStatus::AwaitingTenantReview,
                CaseStatus::TenantActionRequired,
                CaseStatus::OnHold,
            ], true);
    }

    public function abandon(User $user, RepairCase $case): bool
    {
        return $this->resolve($user, $case);
    }

    public function reEngage(User $user, RepairCase $case): bool
    {
        return $this->ownsCase($user, $case)
            && $case->status === CaseStatus::Dormant;
    }

    private function ownsCase(User $user, RepairCase $case): bool
    {
        return $case->tenant_user_id === $user->id;
    }
}

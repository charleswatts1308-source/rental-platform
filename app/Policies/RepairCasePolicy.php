<?php

namespace App\Policies;

use App\Models\RepairCase;
use App\Models\User;

/**
 * Authorisation for tenant access to repair cases. The rule is simple:
 * a case belongs to one tenant (cases.tenant_user_id), and only that
 * tenant may view or act on it. Admin overrides are not yet wired —
 * they will arrive when admin tooling is built.
 *
 * Phase 6a uses viewAny / create / view. Phase 6b adds method-level
 * permissions for action routes (sendNext, hold, resolve, abandon,
 * reEngage), each gated by ownership AND whether the current case
 * status permits the action.
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
}

<?php

namespace App\Policies;

use App\Models\Property;
use App\Models\User;

/**
 * Authorisation for the tenant-side properties registry. Each property
 * belongs to one tenant via properties.registered_by_user_id; only that
 * tenant may view or update it. The case-creation form in
 * CaseController::create relies on the same ownership constraint to
 * decide which properties to offer.
 */
class PropertyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Property $property): bool
    {
        return $property->registered_by_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Property $property): bool
    {
        return $property->registered_by_user_id === $user->id;
    }
}

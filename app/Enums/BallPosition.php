<?php

namespace App\Enums;

/**
 * Silence-model ball position. Identifies which party "owes" the next
 * move on a case — the one whose silence the clock measures.
 *
 * Derivation is the pure message-direction rule (silence-phase-2a
 * ruling a): the party that did NOT send the most recent
 * case_messages row holds the ball. Status acts as a no-clock veto
 * only for Open / OnHold / Resolved / Abandoned / Dormant.
 */
enum BallPosition: string
{
    case Landlord = 'landlord';
    case Tenant = 'tenant';
}

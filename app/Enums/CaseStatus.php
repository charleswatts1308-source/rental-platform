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
}

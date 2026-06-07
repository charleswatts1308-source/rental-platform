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
}

<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;

it('permits every transition listed in the design doc', function (CaseStatus $from, CaseStatus $to) {
    expect(RepairCase::isTransitionAllowed($from, $to))->toBeTrue();
})->with([
    'open → awaiting_landlord' => [CaseStatus::Open, CaseStatus::AwaitingLandlord],
    'awaiting_landlord → awaiting_tenant_review' => [CaseStatus::AwaitingLandlord, CaseStatus::AwaitingTenantReview],
    'awaiting_landlord → on_hold' => [CaseStatus::AwaitingLandlord, CaseStatus::OnHold],
    'awaiting_landlord → resolved' => [CaseStatus::AwaitingLandlord, CaseStatus::Resolved],
    'awaiting_landlord → abandoned' => [CaseStatus::AwaitingLandlord, CaseStatus::Abandoned],
    // Phase 3 D8 — tenant reply transitions to awaiting_landlord uniformly.
    'awaiting_tenant_review → awaiting_landlord (tenant_replied)' => [CaseStatus::AwaitingTenantReview, CaseStatus::AwaitingLandlord],
    'awaiting_tenant_review → on_hold' => [CaseStatus::AwaitingTenantReview, CaseStatus::OnHold],
    'awaiting_tenant_review → resolved' => [CaseStatus::AwaitingTenantReview, CaseStatus::Resolved],
    'awaiting_tenant_review → abandoned' => [CaseStatus::AwaitingTenantReview, CaseStatus::Abandoned],
    // Phase 3 — dormancy fires from awaiting_tenant_review via the silence sweep.
    'awaiting_tenant_review → dormant' => [CaseStatus::AwaitingTenantReview, CaseStatus::Dormant],
    // Phase 3 — hold expiry absorbed: OnHold past hold_until → AwaitingLandlord direct.
    'on_hold → awaiting_landlord (hold_expired)' => [CaseStatus::OnHold, CaseStatus::AwaitingLandlord],
    'on_hold → awaiting_tenant_review (inbound while held)' => [CaseStatus::OnHold, CaseStatus::AwaitingTenantReview],
    'on_hold → resolved' => [CaseStatus::OnHold, CaseStatus::Resolved],
    'on_hold → abandoned' => [CaseStatus::OnHold, CaseStatus::Abandoned],
    // Phase 3 D8 — dormant revival via tenant reply within the revival window.
    'dormant → awaiting_landlord (revived)' => [CaseStatus::Dormant, CaseStatus::AwaitingLandlord],
    'dormant → awaiting_tenant_review (landlord inbound)' => [CaseStatus::Dormant, CaseStatus::AwaitingTenantReview],
    // Phase 3 D0.3 — "it got fixed while I was away" must work directly.
    'dormant → resolved' => [CaseStatus::Dormant, CaseStatus::Resolved],
    'dormant → abandoned' => [CaseStatus::Dormant, CaseStatus::Abandoned],
]);

it('rejects same-status no-op transitions', function (CaseStatus $status) {
    expect(RepairCase::isTransitionAllowed($status, $status))->toBeFalse();
})->with([
    [CaseStatus::Open],
    [CaseStatus::AwaitingLandlord],
    [CaseStatus::AwaitingTenantReview],
    [CaseStatus::OnHold],
    [CaseStatus::Dormant],
    [CaseStatus::Resolved],
    [CaseStatus::Abandoned],
]);

it('rejects all transitions out of terminal statuses', function (CaseStatus $terminal, CaseStatus $to) {
    expect(RepairCase::isTransitionAllowed($terminal, $to))->toBeFalse();
})->with(function () {
    $cases = [];
    foreach ([CaseStatus::Resolved, CaseStatus::Abandoned] as $terminal) {
        foreach (CaseStatus::cases() as $to) {
            if ($to === $terminal) {
                continue;
            }
            $cases[$terminal->value.' → '.$to->value] = [$terminal, $to];
        }
    }
    return $cases;
});

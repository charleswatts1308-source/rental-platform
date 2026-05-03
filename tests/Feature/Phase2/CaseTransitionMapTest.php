<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;

it('permits every transition listed in the design doc', function (CaseStatus $from, CaseStatus $to) {
    expect(RepairCase::isTransitionAllowed($from, $to))->toBeTrue();
})->with([
    'open → awaiting_landlord' => [CaseStatus::Open, CaseStatus::AwaitingLandlord],
    'awaiting_landlord → awaiting_tenant_review' => [CaseStatus::AwaitingLandlord, CaseStatus::AwaitingTenantReview],
    'awaiting_landlord → tenant_action_required' => [CaseStatus::AwaitingLandlord, CaseStatus::TenantActionRequired],
    'awaiting_landlord → resolved' => [CaseStatus::AwaitingLandlord, CaseStatus::Resolved],
    'awaiting_landlord → abandoned' => [CaseStatus::AwaitingLandlord, CaseStatus::Abandoned],
    'awaiting_tenant_review → tenant_action_required' => [CaseStatus::AwaitingTenantReview, CaseStatus::TenantActionRequired],
    'awaiting_tenant_review → on_hold' => [CaseStatus::AwaitingTenantReview, CaseStatus::OnHold],
    'awaiting_tenant_review → resolved' => [CaseStatus::AwaitingTenantReview, CaseStatus::Resolved],
    'awaiting_tenant_review → abandoned' => [CaseStatus::AwaitingTenantReview, CaseStatus::Abandoned],
    'tenant_action_required → awaiting_landlord' => [CaseStatus::TenantActionRequired, CaseStatus::AwaitingLandlord],
    'tenant_action_required → on_hold' => [CaseStatus::TenantActionRequired, CaseStatus::OnHold],
    'tenant_action_required → resolved' => [CaseStatus::TenantActionRequired, CaseStatus::Resolved],
    'tenant_action_required → abandoned' => [CaseStatus::TenantActionRequired, CaseStatus::Abandoned],
    'tenant_action_required → dormant' => [CaseStatus::TenantActionRequired, CaseStatus::Dormant],
    'on_hold → tenant_action_required' => [CaseStatus::OnHold, CaseStatus::TenantActionRequired],
    'on_hold → awaiting_tenant_review' => [CaseStatus::OnHold, CaseStatus::AwaitingTenantReview],
    'on_hold → resolved' => [CaseStatus::OnHold, CaseStatus::Resolved],
    'on_hold → abandoned' => [CaseStatus::OnHold, CaseStatus::Abandoned],
    'dormant → tenant_action_required' => [CaseStatus::Dormant, CaseStatus::TenantActionRequired],
    'dormant → abandoned' => [CaseStatus::Dormant, CaseStatus::Abandoned],
]);

it('rejects same-status no-op transitions', function (CaseStatus $status) {
    expect(RepairCase::isTransitionAllowed($status, $status))->toBeFalse();
})->with([
    [CaseStatus::Open],
    [CaseStatus::AwaitingLandlord],
    [CaseStatus::AwaitingTenantReview],
    [CaseStatus::TenantActionRequired],
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

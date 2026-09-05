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
    // D14 — never-engaged ladder exhaustion (design doc D5).
    'awaiting_landlord → escalation_exhausted' => [CaseStatus::AwaitingLandlord, CaseStatus::EscalationExhausted],
    // D14 allow-reply revival, mirroring dormant's split.
    'escalation_exhausted → awaiting_landlord (tenant revival)' => [CaseStatus::EscalationExhausted, CaseStatus::AwaitingLandlord],
    'escalation_exhausted → awaiting_tenant_review (landlord email revival)' => [CaseStatus::EscalationExhausted, CaseStatus::AwaitingTenantReview],
    'escalation_exhausted → resolved' => [CaseStatus::EscalationExhausted, CaseStatus::Resolved],
    'escalation_exhausted → abandoned' => [CaseStatus::EscalationExhausted, CaseStatus::Abandoned],
    // D17.8 (#25) — a permanent delivery failure stops a case that is
    // still RUNNING. These five are the running statuses.
    'open → contact_failed' => [CaseStatus::Open, CaseStatus::ContactFailed],
    'awaiting_landlord → contact_failed' => [CaseStatus::AwaitingLandlord, CaseStatus::ContactFailed],
    'awaiting_tenant_review → contact_failed' => [CaseStatus::AwaitingTenantReview, CaseStatus::ContactFailed],
    'on_hold → contact_failed' => [CaseStatus::OnHold, CaseStatus::ContactFailed],
    'dormant → contact_failed' => [CaseStatus::Dormant, CaseStatus::ContactFailed],
    // D17.8 — the one exit. The tenant may close their own case.
    'contact_failed → abandoned' => [CaseStatus::ContactFailed, CaseStatus::Abandoned],
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
    [CaseStatus::EscalationExhausted],
    [CaseStatus::ContactFailed],
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


/*
|--------------------------------------------------------------------------
| D17.8 (#25) — contact_failed
|--------------------------------------------------------------------------
|
| A bounce stops a case that is still running; it does not reach back into
| one that has already stopped. resolved and abandoned are covered by the
| terminal-status sweep above, which walks every CaseStatus and so picked
| up contact_failed automatically. escalation_exhausted is NOT terminal in
| that sense — it has permitted exits — so its refusal needs asserting
| explicitly or nothing would catch it being added.
|
*/

it('refuses contact_failed from a case that has ALREADY stopped', function (CaseStatus $from) {
    expect(RepairCase::isTransitionAllowed($from, CaseStatus::ContactFailed))->toBeFalse();
})->with([
    // Ended by exhausting the ladder. A late bounce must not overwrite WHY
    // the case ended; the case_events row still records the failure.
    'escalation_exhausted' => [CaseStatus::EscalationExhausted],
    // Ended by a decision someone made.
    'resolved' => [CaseStatus::Resolved],
    'abandoned' => [CaseStatus::Abandoned],
]);

it('allows exactly ONE exit from contact_failed, and it is abandoned', function () {
    $permitted = array_values(array_filter(
        CaseStatus::cases(),
        fn (CaseStatus $to): bool => RepairCase::isTransitionAllowed(CaseStatus::ContactFailed, $to),
    ));

    expect($permitted)->toBe([CaseStatus::Abandoned]);
});

it('does not revive contact_failed on a reply from either party', function (CaseStatus $to) {
    // Unlike dormant and escalation_exhausted, which both allow-reply back
    // into the correspondence. The address is broken: a reply from it is not
    // expected, and D17.3's tenant-taken copy is the route forward.
    expect(RepairCase::isTransitionAllowed(CaseStatus::ContactFailed, $to))->toBeFalse();
})->with([
    'tenant reply → awaiting_landlord' => [CaseStatus::AwaitingLandlord],
    'landlord email → awaiting_tenant_review' => [CaseStatus::AwaitingTenantReview],
    'resolved' => [CaseStatus::Resolved],
]);
<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permits dormant → awaiting_tenant_review (landlord activity wakes the case)', function () {
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::AwaitingTenantReview))
        ->toBeTrue();
});

it('writes inbound_received as the canonical event when waking a dormant case via landlord inbound', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::Dormant]);

    $case->transitionTo(CaseStatus::AwaitingTenantReview);

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($case->events()->orderByDesc('id')->first()->event_type)->toBe('inbound_received');
});

it('permits the Phase 3 dormant exit transitions', function () {
    // Phase 3 D8 — tenant reply revives the case direct to awaiting_landlord.
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::AwaitingLandlord))->toBeTrue();
    // Phase 3 D0.3 — direct resolve ("it got fixed while I was away").
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::Resolved))->toBeTrue();
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::Abandoned))->toBeTrue();
});

it('rejects on_hold from dormant — revive first then hold', function () {
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::OnHold))->toBeFalse();
});

<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permits dormant → awaiting_tenant_review (landlord activity wakes the case)', function () {
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::AwaitingTenantReview))
        ->toBeTrue();
});

it('writes inbound_received as the canonical event when waking a dormant case', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::Dormant]);

    $case->transitionTo(CaseStatus::AwaitingTenantReview);

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($case->events()->orderByDesc('id')->first()->event_type)->toBe('inbound_received');
});

it('still permits the existing dormant transitions (tenant_action_required, abandoned)', function () {
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::TenantActionRequired))->toBeTrue();
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::Abandoned))->toBeTrue();
});

it('still rejects other dormant transitions', function () {
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::AwaitingLandlord))->toBeFalse();
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::OnHold))->toBeFalse();
    expect(RepairCase::isTransitionAllowed(CaseStatus::Dormant, CaseStatus::Resolved))->toBeFalse();
});

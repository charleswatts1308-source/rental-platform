<?php

use App\Enums\CaseStatus;
use App\Exceptions\InvalidCaseTransitionException;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('throws InvalidCaseTransitionException for illegal transitions', function (
    CaseStatus $from,
    CaseStatus $to,
) {
    $case = RepairCase::factory()->create(['status' => $from]);

    expect(fn () => $case->transitionTo($to))
        ->toThrow(InvalidCaseTransitionException::class);
})->with([
    'open → resolved (skip stages)' => [CaseStatus::Open, CaseStatus::Resolved],
    'open → on_hold (no notice yet)' => [CaseStatus::Open, CaseStatus::OnHold],
    'open → tenant_action_required (skip stages)' => [CaseStatus::Open, CaseStatus::TenantActionRequired],
    'open → dormant' => [CaseStatus::Open, CaseStatus::Dormant],
    'awaiting_landlord → on_hold (only review/action allow hold)' => [CaseStatus::AwaitingLandlord, CaseStatus::OnHold],
    'awaiting_landlord → dormant' => [CaseStatus::AwaitingLandlord, CaseStatus::Dormant],
    'awaiting_tenant_review → awaiting_landlord (must go via action)' => [CaseStatus::AwaitingTenantReview, CaseStatus::AwaitingLandlord],
    'awaiting_tenant_review → dormant' => [CaseStatus::AwaitingTenantReview, CaseStatus::Dormant],
    'tenant_action_required → awaiting_tenant_review' => [CaseStatus::TenantActionRequired, CaseStatus::AwaitingTenantReview],
    'on_hold → awaiting_landlord (must go via action)' => [CaseStatus::OnHold, CaseStatus::AwaitingLandlord],
    'on_hold → dormant' => [CaseStatus::OnHold, CaseStatus::Dormant],
    'dormant → awaiting_landlord' => [CaseStatus::Dormant, CaseStatus::AwaitingLandlord],
    'dormant → on_hold' => [CaseStatus::Dormant, CaseStatus::OnHold],
    'dormant → resolved (must abandon, not resolve)' => [CaseStatus::Dormant, CaseStatus::Resolved],
    'dormant → awaiting_tenant_review' => [CaseStatus::Dormant, CaseStatus::AwaitingTenantReview],
    'resolved → awaiting_landlord (terminal)' => [CaseStatus::Resolved, CaseStatus::AwaitingLandlord],
    'resolved → open (terminal)' => [CaseStatus::Resolved, CaseStatus::Open],
    'abandoned → awaiting_landlord (terminal)' => [CaseStatus::Abandoned, CaseStatus::AwaitingLandlord],
    'abandoned → resolved (terminal)' => [CaseStatus::Abandoned, CaseStatus::Resolved],
]);

it('does not change status when an illegal transition is attempted', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::Open]);

    try {
        $case->transitionTo(CaseStatus::Resolved);
    } catch (InvalidCaseTransitionException) {
        // expected
    }

    expect($case->fresh()->status)->toBe(CaseStatus::Open);
});

it('does not write an event when an illegal transition is attempted', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::Open]);
    $eventCountBefore = $case->events()->count();

    try {
        $case->transitionTo(CaseStatus::Resolved);
    } catch (InvalidCaseTransitionException) {
        // expected
    }

    expect($case->fresh()->events()->count())->toBe($eventCountBefore);
});

it('throws the illegalTransition variant of the exception, not directWrite', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::Open]);

    try {
        $case->transitionTo(CaseStatus::Resolved);
        expect()->fail('Expected InvalidCaseTransitionException');
    } catch (InvalidCaseTransitionException $e) {
        expect($e->getMessage())->toContain("Cannot transition case from 'open' to 'resolved'");
    }
});

<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('sets closed_at when transitioning to resolved', function (CaseStatus $from) {
    $case = RepairCase::factory()->create([
        'status' => $from,
        'closed_at' => null,
    ]);

    $case->transitionTo(CaseStatus::Resolved);

    expect($case->fresh()->closed_at)->toBeInstanceOf(Carbon::class);
})->with([
    [CaseStatus::AwaitingLandlord],
    [CaseStatus::AwaitingTenantReview],
    [CaseStatus::TenantActionRequired],
    [CaseStatus::OnHold],
]);

it('sets closed_at when transitioning to abandoned', function (CaseStatus $from) {
    $case = RepairCase::factory()->create([
        'status' => $from,
        'closed_at' => null,
    ]);

    $case->transitionTo(CaseStatus::Abandoned);

    expect($case->fresh()->closed_at)->toBeInstanceOf(Carbon::class);
})->with([
    [CaseStatus::AwaitingLandlord],
    [CaseStatus::AwaitingTenantReview],
    [CaseStatus::TenantActionRequired],
    [CaseStatus::OnHold],
    [CaseStatus::Dormant],
]);

it('sets hold_until from context when transitioning to on_hold', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingTenantReview,
        'hold_until' => null,
    ]);
    $holdUntil = now()->addDays(14);

    $case->transitionTo(CaseStatus::OnHold, ['hold_until' => $holdUntil]);

    expect($case->fresh()->hold_until->toIso8601String())->toBe($holdUntil->toIso8601String());
});

it('does not clear hold_until when transitioning out of on_hold', function () {
    $holdUntil = now()->subDay();
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => $holdUntil,
    ]);

    $case->transitionTo(CaseStatus::TenantActionRequired);

    expect($case->fresh()->hold_until)->not->toBeNull();
    expect($case->fresh()->hold_until->toIso8601String())->toBe($holdUntil->toIso8601String());
});

it('increments current_stage when tenant_action_required → awaiting_landlord', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::TenantActionRequired,
        'current_stage' => 2,
    ]);

    $case->transitionTo(CaseStatus::AwaitingLandlord);

    expect($case->fresh()->current_stage)->toBe(3);
});

it('does not increment current_stage on other transitions', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => 2,
    ]);

    $case->transitionTo(CaseStatus::AwaitingTenantReview);

    expect($case->fresh()->current_stage)->toBe(2);
});

it('does not set closed_at on non-terminal transitions', function () {
    // Uses awaiting_landlord → awaiting_tenant_review post-2b
    // (the awaiting_landlord → tenant_action_required transition was
    // demolished alongside SweepEscalations).
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'closed_at' => null,
    ]);

    $case->transitionTo(CaseStatus::AwaitingTenantReview);

    expect($case->fresh()->closed_at)->toBeNull();
});

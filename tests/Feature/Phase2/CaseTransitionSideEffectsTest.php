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
    [CaseStatus::OnHold],
    [CaseStatus::Dormant],
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

    $case->transitionTo(CaseStatus::AwaitingLandlord);

    expect($case->fresh()->hold_until)->not->toBeNull();
    expect($case->fresh()->hold_until->toIso8601String())->toBe($holdUntil->toIso8601String());
});

it('stamps dormant_at when transitioning to dormant', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingTenantReview,
        'dormant_at' => null,
    ]);

    $case->transitionTo(CaseStatus::Dormant);

    expect($case->fresh()->dormant_at)->toBeInstanceOf(Carbon::class);
});

it('clears dormant_at when revived out of dormant', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::Dormant,
        'dormant_at' => now()->subDays(10),
    ]);

    $case->transitionTo(CaseStatus::AwaitingLandlord);

    expect($case->fresh()->dormant_at)->toBeNull();
});

it('does not set closed_at on non-terminal transitions', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'closed_at' => null,
    ]);

    $case->transitionTo(CaseStatus::AwaitingTenantReview);

    expect($case->fresh()->closed_at)->toBeNull();
});

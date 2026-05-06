<?php

use App\Enums\CaseStatus;
use App\Exceptions\InvalidCaseTransitionException;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects direct property assignment to status outside transitionTo', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->status = CaseStatus::Resolved;
    $case->save();
})->throws(InvalidCaseTransitionException::class);

it('rejects mass-assignment to status via update() outside transitionTo', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->update(['status' => CaseStatus::Resolved]);
})->throws(InvalidCaseTransitionException::class);

it('throws the directWrite variant of the exception, not illegalTransition', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    try {
        $case->status = CaseStatus::Resolved;
        $case->save();
        expect()->fail('Expected InvalidCaseTransitionException');
    } catch (InvalidCaseTransitionException $e) {
        expect($e->getMessage())->toContain('Direct writes');
    }
});

it('allows updating other columns directly without status change', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->next_stage_eligible_at = now()->addDays(14);
    $case->save();

    expect($case->fresh()->next_stage_eligible_at)->not->toBeNull();
});

it('allows transitionTo to change status without throwing', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->transitionTo(CaseStatus::Resolved);

    expect($case->fresh()->status)->toBe(CaseStatus::Resolved);
});

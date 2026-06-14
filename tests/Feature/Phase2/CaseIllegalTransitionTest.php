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
    'open → dormant' => [CaseStatus::Open, CaseStatus::Dormant],
    // NB: awaiting_landlord → dormant WAS illegal pre-D15; it is now the
    // legal unauthorised-tail edge for engagement-gated escalation. See the
    // positive assertion below and tests/Feature/D15.
    'on_hold → dormant (hold pauses the silence clock)' => [CaseStatus::OnHold, CaseStatus::Dormant],
    'dormant → on_hold (revive first, then hold)' => [CaseStatus::Dormant, CaseStatus::OnHold],
    'resolved → awaiting_landlord (terminal)' => [CaseStatus::Resolved, CaseStatus::AwaitingLandlord],
    'resolved → open (terminal)' => [CaseStatus::Resolved, CaseStatus::Open],
    'abandoned → awaiting_landlord (terminal)' => [CaseStatus::Abandoned, CaseStatus::AwaitingLandlord],
    'abandoned → resolved (terminal)' => [CaseStatus::Abandoned, CaseStatus::Resolved],
    // D14 — escalation_exhausted is allow-reply + closeable, but NOT a free
    // door: only the four legal out-edges exist; on_hold / re-escalation are
    // not among them.
    'escalation_exhausted → on_hold (no re-pausing a closed ladder)' => [CaseStatus::EscalationExhausted, CaseStatus::OnHold],
    'escalation_exhausted → open' => [CaseStatus::EscalationExhausted, CaseStatus::Open],
    'escalation_exhausted → escalation_exhausted (self)' => [CaseStatus::EscalationExhausted, CaseStatus::EscalationExhausted],
]);

it('allows awaiting_landlord → dormant (D15 engagement-gated unauthorised tail)', function () {
    expect(RepairCase::isTransitionAllowed(CaseStatus::AwaitingLandlord, CaseStatus::Dormant))->toBeTrue();
});

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

<?php

use App\Enums\CaseStatus;

/**
 * Snag #47 — the guard that makes an unclassified status impossible.
 *
 * Case status handling used to be OPT-OUT in two independent places
 * (SilenceSweep's whereNotIn, SilenceClock's NO_CLOCK_STATUSES). A status
 * not named in those lists was swept AND given a silence clock — so a
 * forgotten entry produced an actively escalating case rather than an
 * inert one, on a product whose output is an evidential record.
 *
 * The classification now lives on CaseStatus as `match` expressions with
 * no `default` arm. These tests walk every enum case so that adding one
 * fails HERE, loudly, instead of in a live sweep.
 */
it('classifies every status for sweeping, with no unhandled case', function () {
    foreach (CaseStatus::cases() as $status) {
        // Throws \UnhandledMatchError if the status was added to the enum
        // without being classified. That failure is the point of this test.
        expect($status->isSweepable())->toBeBool();
    }
});

it('classifies every status for the silence clock, with no unhandled case', function () {
    foreach (CaseStatus::cases() as $status) {
        expect($status->hasSilenceClock())->toBeBool();
    }
});

it('classifies every status as closed or not, with no unhandled case', function () {
    foreach (CaseStatus::cases() as $status) {
        expect($status->isClosedStatus())->toBeBool();
    }
});

it('pins the sweepable set — a change here must be deliberate', function () {
    expect(CaseStatus::sweepable())->toBe([
        CaseStatus::Open,
        CaseStatus::AwaitingLandlord,
        CaseStatus::AwaitingTenantReview,
        CaseStatus::OnHold,
    ]);
});

it('pins the clock-bearing set — a change here must be deliberate', function () {
    $withClock = array_values(array_filter(
        CaseStatus::cases(),
        fn (CaseStatus $s): bool => $s->hasSilenceClock(),
    ));

    expect($withClock)->toBe([
        CaseStatus::AwaitingLandlord,
        CaseStatus::AwaitingTenantReview,
    ]);
});

it('keeps a case that is swept but clockless — OnHold is not a contradiction', function () {
    // The sweep loads held cases in order to RELEASE an expired hold, but no
    // silence accrues while the case is held. The two predicates are
    // genuinely independent and this is the case that proves it.
    expect(CaseStatus::OnHold->isSweepable())->toBeTrue();
    expect(CaseStatus::OnHold->hasSilenceClock())->toBeFalse();
});

it('never sweeps or clocks a fully closed status', function () {
    foreach (CaseStatus::cases() as $status) {
        if (! $status->isClosedStatus()) {
            continue;
        }

        expect($status->isSweepable())->toBeFalse();
        expect($status->hasSilenceClock())->toBeFalse();
    }
});

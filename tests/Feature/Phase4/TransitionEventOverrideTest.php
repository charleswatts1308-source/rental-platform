<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the canonical event_type from TRANSITIONS when no override is provided', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->transitionTo(CaseStatus::AwaitingTenantReview);

    $event = $case->events()->orderByDesc('id')->first();
    expect($event->event_type)->toBe('inbound_received');
});

it('writes the overridden event_type when context.event_type_override is set', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->transitionTo(CaseStatus::AwaitingTenantReview, [
        'event_type_override' => 'inbound_quarantined',
    ]);

    $event = $case->events()->orderByDesc('id')->first();
    expect($event->event_type)->toBe('inbound_quarantined');
});

it('still validates the (from, to) transition even when overriding event_type', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::Resolved]);

    $case->transitionTo(CaseStatus::AwaitingLandlord, [
        'event_type_override' => 'inbound_received',
    ]);
})->throws(\App\Exceptions\InvalidCaseTransitionException::class);

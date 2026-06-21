<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const PREDICATE_SNAPSHOT = [
    'escalation.interval_days' => 14,
    'escalation.max_notices' => 4,
    'nudge.first_days' => 10,
    'nudge.second_days' => 20,
    'nudge.dormancy_days' => 30,
];

function ownedCase(User $tenant, array $attributes = []): RepairCase
{
    return RepairCase::factory()->create(array_merge([
        'tenant_user_id' => $tenant->id,
    ], $attributes));
}

// ---- model predicate (the shared source of truth) --------------------

it('shows next escalation only while the landlord clock counts down', function () {
    $case = RepairCase::factory()->make([
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
        'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
    ]);

    expect($case->showsNextEscalation())->toBeTrue();
    expect($case->nextEscalationDate()->toDateString())->toBe('2026-06-15');
});

it('suppresses next escalation on on_hold (#14)', function () {
    $case = RepairCase::factory()->make([
        'status' => CaseStatus::OnHold,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
        'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
    ]);

    expect($case->showsNextEscalation())->toBeFalse();
    expect($case->nextEscalationDate())->toBeNull();
});

it('suppresses next escalation on terminal and exhausted states (#21-tail)', function () {
    foreach ([CaseStatus::Resolved, CaseStatus::Abandoned, CaseStatus::EscalationExhausted, CaseStatus::Dormant] as $status) {
        $case = RepairCase::factory()->make([
            'status' => $status,
            'ball_with' => 'landlord',
            'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
            'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
        ]);

        expect($case->showsNextEscalation())->toBeFalse("status {$status->value}");
    }
});

it('shows hold_until only while on_hold (#15)', function () {
    $onHold = RepairCase::factory()->make([
        'status' => CaseStatus::OnHold,
        'hold_until' => Carbon::parse('2026-07-01'),
    ]);
    $released = RepairCase::factory()->make([
        'status' => CaseStatus::AwaitingLandlord, // hold released, column kept
        'hold_until' => Carbon::parse('2026-07-01'),
    ]);

    expect($onHold->showsHoldUntil())->toBeTrue();
    expect($released->showsHoldUntil())->toBeFalse();
});

// ---- tenant view still behaves correctly with the predicate ----------

it('tenant case page hides Next escalation on on_hold (#14)', function () {
    $tenant = User::factory()->create();
    $case = ownedCase($tenant, [
        'status' => CaseStatus::OnHold,
        'ball_with' => 'landlord',
        'hold_until' => Carbon::parse('2026-07-01'),
        'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
        'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
    ]);

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertDontSee('Next escalation')
        ->assertSee('Hold until');
});

it('tenant case page hides stale hold_until once released (#15)', function () {
    $tenant = User::factory()->create();
    $case = ownedCase($tenant, [
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'hold_until' => Carbon::parse('2026-07-01'), // historical, hold released
        'silence_clock_started_at' => Carbon::parse('2026-06-01 09:00:00'),
        'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
    ]);

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertSee('Next escalation')
        ->assertDontSee('Hold until');
});

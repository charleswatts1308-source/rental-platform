<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
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
        ->assertSee('Paused until');
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
        ->assertDontSee('Paused until');
});

// ---- gap 1: engaged label wording (the visible D15 payoff) ------------

it('tenant page renders the engaged "Next notice (with your go-ahead)" wording, not plain "Next escalation" (gap 1)', function () {
    $tenant = User::factory()->create();
    $case = ownedCase($tenant, [
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'landlord_engaged' => true,
        // Clock live but NOT expired -> not authorisation-pending, so the
        // line shows (with the engaged wording).
        'silence_clock_started_at' => Carbon::now()->subDays(3),
        'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
    ]);
    attachOutboundNotice($case);

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertSee('Next notice (with your go-ahead)')
        ->assertDontSee('Next escalation');
});

// ---- gap 2: authorisation-pending suppresses the line ----------------

it('tenant page suppresses the escalation line when authorisation is pending (gap 2)', function () {
    $tenant = User::factory()->create();
    $case = ownedCase($tenant, [
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'landlord_engaged' => true,
        'current_stage' => 1,
        // Clock expired (>= interval) with counter < max -> authorisationPending.
        'silence_clock_started_at' => Carbon::now()->subDays(20),
        'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
    ]);
    attachOutboundNotice($case); // counter = 1 (< max 4); ball -> landlord

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertDontSee('Next escalation')
        ->assertDontSee('Next notice (with your go-ahead)')
        // The authorise prompt is what renders instead.
        ->assertSee('without your say-so');
});

// ---- gap 3: no escalation line on dormant / closed / exhausted -------

it('tenant page renders no escalation line on dormant, resolved and abandoned states (gap 3)', function () {
    $tenant = User::factory()->create();

    foreach ([CaseStatus::Dormant, CaseStatus::Resolved, CaseStatus::Abandoned] as $status) {
        $case = ownedCase($tenant, [
            'status' => $status,
            'ball_with' => 'landlord',
            'silence_clock_started_at' => Carbon::now()->subDays(20),
            'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
        ]);

        $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
            ->assertOk()
            ->assertDontSee('Next escalation', "status {$status->value}")
            ->assertDontSee('Next notice (with your go-ahead)');
    }
});

it('tenant page on exhausted: no escalation line + #21 panel (reply/resolve/abandon present, stance absent) (gap 3)', function () {
    $tenant = User::factory()->create();
    $case = ownedCase($tenant, [
        'status' => CaseStatus::EscalationExhausted,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::now()->subDays(60),
        'silence_settings_snapshot' => PREDICATE_SNAPSHOT,
    ]);

    $this->actingAs($tenant)->get("/cases/{$case->url_slug}")
        ->assertOk()
        ->assertDontSee('Next escalation')
        ->assertDontSee('Next notice (with your go-ahead)')
        ->assertSee('Send reply')
        ->assertSee('Mark resolved')
        ->assertSee('Abandon this case')
        ->assertDontSee('How do you see this case?');
});

/** Attach an outbound system notice so ballFor() resolves to landlord. */
function attachOutboundNotice(RepairCase $case, int $stage = 1): void
{
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => $stage,
    ]);
}

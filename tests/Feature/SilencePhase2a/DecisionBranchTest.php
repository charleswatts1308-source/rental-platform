<?php

use App\Enums\BallPosition;
use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use App\Services\Silence\IntendedAction;
use App\Services\Silence\SilenceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * Every verdict branch in SilenceClock::evaluate, exercised explicitly.
 *
 * Default snapshot used throughout (matches the seeded SettingSeeder):
 *   escalation.interval_days = 14
 *   escalation.max_notices   = 4
 *   nudge.first_days         = 10
 *   nudge.second_days        = 20
 *   nudge.dormancy_days      = 30
 */
const DEFAULT_SNAPSHOT = [
    'escalation.interval_days' => 14,
    'escalation.max_notices' => 4,
    'nudge.first_days' => 10,
    'nudge.second_days' => 20,
    'nudge.dormancy_days' => 30,
];

function landlordSideCase(?Carbon $startedAt = null, int $lettersAlreadySent = 1, array $snapshot = DEFAULT_SNAPSHOT): RepairCase
{
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => $startedAt ?? Carbon::now(),
        'silence_settings_snapshot' => $snapshot,
    ]);
    for ($i = 1; $i <= $lettersAlreadySent; $i++) {
        CaseMessage::factory()->create([
            'case_id' => $case->id,
            'direction' => MessageDirection::Outbound,
            'sender_role' => SenderRole::System,
            'stage_at_send' => $i,
        ]);
    }

    return $case->fresh();
}

function tenantSideCase(?Carbon $startedAt = null, array $snapshot = DEFAULT_SNAPSHOT): RepairCase
{
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingTenantReview,
        'ball_with' => 'tenant',
        'silence_clock_started_at' => $startedAt ?? Carbon::now(),
        'silence_settings_snapshot' => $snapshot,
    ]);
    // The inbound message is what makes ball=tenant under the
    // message-direction rule.
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);

    return $case->fresh();
}

// ─── Landlord side ──────────────────────────────────────────────

it('no clock: status is Open → no_action with reasoning naming the status', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::Open]);
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::NoAction);
    expect($verdict->ballWith)->toBeNull();
    expect($verdict->silenceDays)->toBeNull();
    expect($verdict->reasoning)->toContain('no clock');
});

it('landlord-side: clock not expired → no_action with counter reported', function () {
    $case = landlordSideCase(startedAt: Carbon::now()->subDays(10), lettersAlreadySent: 1);
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::NoAction);
    expect($verdict->ballWith)->toBe(BallPosition::Landlord);
    expect($verdict->silenceDays)->toBe(10);
    expect($verdict->escalationCounterValue)->toBe(1);
    expect($verdict->reasoning)->toContain('10/14');
});

it('landlord-side: clock expired, counter below max → send_escalation with template resolved', function () {
    $case = landlordSideCase(startedAt: Carbon::now()->subDays(15), lettersAlreadySent: 1);
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::SendEscalation);
    expect($verdict->silenceDays)->toBe(15);
    expect($verdict->escalationCounterValue)->toBe(1);
    expect($verdict->intendedLetterTemplate)->not->toBeNull();
    expect($verdict->intendedLetterTemplate->code)->toBe('landlord_wakeup_generic');
});

it('landlord-side: clock expired at max counter → transition_exhausted_intent', function () {
    $case = landlordSideCase(startedAt: Carbon::now()->subDays(15), lettersAlreadySent: 4);
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::TransitionExhaustedIntent);
    expect($verdict->escalationCounterValue)->toBe(4);
    expect($verdict->intendedLetterTemplate)->toBeNull();
    expect($verdict->reasoning)->toContain('exhausted');
});

it('landlord-side: D1 fallback recorded in reasoning when no stage-specific row exists', function () {
    // The seeded template is stage=NULL (generic wake-up). For notice
    // number 2 there's no stage=2 row, so the lookup falls back to
    // the generic. Reasoning should make that visible for the operator.
    $case = landlordSideCase(startedAt: Carbon::now()->subDays(15), lettersAlreadySent: 1);
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->reasoning)->toContain('NULL fallback');
});

it('landlord-side: stage-specific template wins over fallback when present', function () {
    LetterTemplate::create([
        'code' => 'landlord_wakeup_stage_2',
        'description' => 'Stage 2 specific',
        'subject' => 'Stage 2',
        'body' => 'Stage 2 body',
        'type' => 'escalation',
        'stage' => 2,
        'active' => true,
    ]);
    $case = landlordSideCase(startedAt: Carbon::now()->subDays(15), lettersAlreadySent: 1);

    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedLetterTemplate->code)->toBe('landlord_wakeup_stage_2');
    expect($verdict->reasoning)->toContain('stage=2 match');
});

// ─── Tenant side ──────────────────────────────────────────────

it('tenant-side: silence below first-nudge threshold → no_action', function () {
    $case = tenantSideCase(startedAt: Carbon::now()->subDays(5));
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::NoAction);
    expect($verdict->ballWith)->toBe(BallPosition::Tenant);
    expect($verdict->silenceDays)->toBe(5);
});

it('tenant-side: silence >= first threshold → send_nudge with nudge_number=1', function () {
    $case = tenantSideCase(startedAt: Carbon::now()->subDays(10));
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::SendNudge);
    expect($verdict->nudgeNumber)->toBe(1);
    expect($verdict->intendedLetterTemplate?->code)->toBe('tenant_nudge_generic');
});

it('tenant-side: silence >= second threshold → send_nudge with nudge_number=2', function () {
    $case = tenantSideCase(startedAt: Carbon::now()->subDays(20));
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::SendNudge);
    expect($verdict->nudgeNumber)->toBe(2);
});

it('tenant-side: silence >= dormancy threshold → transition_dormant_intent', function () {
    $case = tenantSideCase(startedAt: Carbon::now()->subDays(30));
    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::TransitionDormantIntent);
    expect($verdict->intendedLetterTemplate)->toBeNull();
    expect($verdict->reasoning)->toContain('dormant');
});

it('tenant-side: nudge derivation is stateless — re-evaluation at the same horizon yields same nudge_number (ruling c)', function () {
    $case = tenantSideCase(startedAt: Carbon::now()->subDays(10));

    $first = (new SilenceClock)->evaluate($case, Carbon::now());
    $second = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($first->nudgeNumber)->toBe($second->nudgeNumber);
    expect($first->nudgeNumber)->toBe(1);
});

// ─── Edge: ball-bearing status without clock-start data ─────────

it('ball-bearing case without clock-start data → no_action with diagnostic reasoning', function () {
    $contact = \App\Models\LandlordContact::factory()->create();
    $category = \App\Models\RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'silence_clock_started_at' => null,
        'silence_settings_snapshot' => null,
    ]);
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => 1,
    ]);

    $verdict = (new SilenceClock)->evaluate($case->fresh(), Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::NoAction);
    expect($verdict->reasoning)->toContain('wiring gap');
});

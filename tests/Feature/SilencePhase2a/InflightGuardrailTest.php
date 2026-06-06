<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\Setting;
use App\Services\Silence\IntendedAction;
use App\Services\Silence\SilenceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/**
 * D4 in-flight guardrail: a settings change applies only to clocks
 * started AFTER the change. In-flight clocks read from the snapshot
 * stamped onto the case at clock-start; the snapshot is frozen.
 *
 * These tests pin the named behaviours from silence-phase-2a D0.6.
 * Each one runs against the SilenceClock service directly so the
 * guardrail is verified independently of the command/sweep wiring.
 */
function caseWithLandlordClock(int $daysAgo, array $snapshot, int $lettersSent = 1): RepairCase
{
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::now()->subDays($daysAgo),
        'silence_settings_snapshot' => $snapshot,
    ]);
    for ($i = 1; $i <= $lettersSent; $i++) {
        CaseMessage::factory()->create([
            'case_id' => $case->id,
            'direction' => MessageDirection::Outbound,
            'sender_role' => SenderRole::System,
            'stage_at_send' => $i,
        ]);
    }

    return $case->fresh();
}

function caseWithTenantClock(int $daysAgo, array $snapshot): RepairCase
{
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingTenantReview,
        'ball_with' => 'tenant',
        'silence_clock_started_at' => Carbon::now()->subDays($daysAgo),
        'silence_settings_snapshot' => $snapshot,
    ]);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);

    return $case->fresh();
}

it('settings change does NOT affect an in-flight clock — landlord-side', function () {
    // Case started with interval=14; an operator now edits the live
    // setting down to 7; the case is at day 10. Under the OLD setting
    // the clock has not expired (10 < 14); under the NEW setting it
    // would have (10 >= 7). The guardrail must honour the OLD value.
    $case = caseWithLandlordClock(daysAgo: 10, snapshot: [
        'escalation.interval_days' => 14,
        'escalation.max_notices' => 4,
        'nudge.first_days' => 10,
        'nudge.second_days' => 20,
        'nudge.dormancy_days' => 30,
    ]);
    Setting::query()->where('key', 'escalation.interval_days')->update(['value' => '7']);

    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::NoAction);
    expect($verdict->reasoning)->toContain('10/14');
});

it('settings change DOES affect a freshly-started clock', function () {
    // The new clock starts AFTER the setting change; its snapshot
    // captures the new value, so it expires at 7 days.
    Setting::query()->where('key', 'escalation.interval_days')->update(['value' => '7']);

    $case = caseWithLandlordClock(daysAgo: 8, snapshot: SilenceClock::snapshotCurrentSettings());

    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::SendEscalation);
});

it('settings change does NOT affect an in-flight clock — tenant-side', function () {
    $case = caseWithTenantClock(daysAgo: 7, snapshot: [
        'escalation.interval_days' => 14,
        'escalation.max_notices' => 4,
        'nudge.first_days' => 10,
        'nudge.second_days' => 20,
        'nudge.dormancy_days' => 30,
    ]);
    Setting::query()->where('key', 'nudge.first_days')->update(['value' => '5']);

    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::NoAction);
    expect($verdict->reasoning)->toContain('below first-nudge threshold (7/10');
});

it('max_notices change does NOT affect an in-flight escalation cap', function () {
    // Three letters sent, snapshot.max_notices=4 → one more before
    // exhaustion. Operator lowers max to 3 mid-flight. The in-flight
    // clock keeps max=4, so the verdict at expiry is send_escalation
    // (notice 4), NOT transition_exhausted_intent.
    $case = caseWithLandlordClock(daysAgo: 15, lettersSent: 3, snapshot: [
        'escalation.interval_days' => 14,
        'escalation.max_notices' => 4,
        'nudge.first_days' => 10,
        'nudge.second_days' => 20,
        'nudge.dormancy_days' => 30,
    ]);
    Setting::query()->where('key', 'escalation.max_notices')->update(['value' => '3']);

    $verdict = (new SilenceClock)->evaluate($case, Carbon::now());

    expect($verdict->intendedAction)->toBe(IntendedAction::SendEscalation);
    expect($verdict->escalationCounterValue)->toBe(3);
});

it('snapshotCurrentSettings reads live settings — defaults when keys are missing', function () {
    Setting::query()->delete();

    $snapshot = SilenceClock::snapshotCurrentSettings();

    expect($snapshot['escalation.interval_days'])->toBe(14);
    expect($snapshot['escalation.max_notices'])->toBe(4);
    expect($snapshot['nudge.first_days'])->toBe(10);
    expect($snapshot['nudge.second_days'])->toBe(20);
    expect($snapshot['nudge.dormancy_days'])->toBe(30);
});

it('snapshotCurrentSettings reads edited values from live settings', function () {
    Setting::query()->where('key', 'escalation.interval_days')->update(['value' => '21']);
    Setting::query()->where('key', 'nudge.dormancy_days')->update(['value' => '45']);

    $snapshot = SilenceClock::snapshotCurrentSettings();

    expect($snapshot['escalation.interval_days'])->toBe(21);
    expect($snapshot['nudge.dormancy_days'])->toBe(45);
    // Unedited keys still read their (seeded) value.
    expect($snapshot['escalation.max_notices'])->toBe(4);
});

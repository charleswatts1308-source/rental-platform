<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseEvent;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\SilenceShadowLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

const SWEEP_DEFAULT_SNAPSHOT = [
    'escalation.interval_days' => 14,
    'escalation.max_notices' => 4,
    'nudge.first_days' => 10,
    'nudge.second_days' => 20,
    'nudge.dormancy_days' => 30,
];

function sweepCaseLandlord(int $daysAgo = 15): RepairCase
{
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'ball_with' => 'landlord',
        'silence_clock_started_at' => Carbon::now()->subDays($daysAgo),
        'silence_settings_snapshot' => SWEEP_DEFAULT_SNAPSHOT,
    ]);
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => 1,
    ]);

    return $case->fresh();
}

it('pretend-mode sweep sends no mail — even with expired landlord-side clocks', function () {
    // silence-phase-2b: --pretend-today forces full shadow on either
    // side. This test pins the post-2b safety guarantee.
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
    sweepCaseLandlord();   // expired landlord-side; would normally fire

    $future = \Illuminate\Support\Carbon::now()->addDays(15)->toDateString();
    $this->artisan("silence:sweep --pretend-today={$future}")->assertSuccessful();

    Mail::assertNothingQueued();
});

it('sweep makes no status transitions on landlord auto-escalation — case stays awaiting_landlord', function () {
    // silence-phase-2b: auto-escalation sends without transitioning;
    // status remains awaiting_landlord. The "no transitions" property
    // survives even when the sweep DOES send.
    Mail::fake();
    $case = sweepCaseLandlord();
    $statusBefore = $case->status;

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->fresh()->status)->toBe($statusBefore);
});

it('pretend-mode sweep writes no case_events — full shadow on either side', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
    $case = sweepCaseLandlord();
    $eventsBefore = CaseEvent::query()->count();

    $future = \Illuminate\Support\Carbon::now()->addDays(15)->toDateString();
    $this->artisan("silence:sweep --pretend-today={$future}")->assertSuccessful();

    expect(CaseEvent::query()->count())->toBe($eventsBefore);
});

it('shadow sweep writes one silence_shadow_log row per non-terminal case', function () {
    Mail::fake();
    sweepCaseLandlord();    // 1 row
    sweepCaseLandlord(5);   // 1 row (clock not expired)
    RepairCase::factory()->create(['status' => CaseStatus::Resolved, 'closed_at' => now()]);  // skipped
    RepairCase::factory()->create(['status' => CaseStatus::Abandoned, 'closed_at' => now()]); // skipped

    $this->artisan('silence:sweep')->assertSuccessful();

    expect(SilenceShadowLog::query()->count())->toBe(2);
});

it('shadow sweep skips terminal statuses at the query layer — no rows for Resolved/Abandoned', function () {
    Mail::fake();
    RepairCase::factory()->create(['status' => CaseStatus::Resolved, 'closed_at' => now()]);
    RepairCase::factory()->create(['status' => CaseStatus::Abandoned, 'closed_at' => now()]);

    $this->artisan('silence:sweep')->assertSuccessful();

    expect(SilenceShadowLog::query()->count())->toBe(0);
});

it('records send_escalation intent with letter template, ball, days, counter, reasoning', function () {
    Mail::fake();
    $case = sweepCaseLandlord();

    $this->artisan('silence:sweep')->assertSuccessful();

    $row = SilenceShadowLog::query()->where('case_id', $case->id)->sole();
    expect($row->intended_action)->toBe('send_escalation');
    expect($row->ball_with)->toBe('landlord');
    expect($row->silence_days)->toBe(15);
    expect($row->escalation_counter_value)->toBe(1);
    expect($row->intended_letter_template_id)->not->toBeNull();
    expect($row->reasoning)->toContain('template id=');
});

it('real-clock rows: is_pretend=false, pretend_today=null', function () {
    Mail::fake();
    sweepCaseLandlord();

    $this->artisan('silence:sweep')->assertSuccessful();

    $row = SilenceShadowLog::query()->sole();
    expect($row->is_pretend)->toBeFalse();
    expect($row->pretend_today)->toBeNull();
});

it('--pretend-today: rows are marked is_pretend=true and stamp the date', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
    sweepCaseLandlord(daysAgo: 5);   // not expired today
    $future = Carbon::now()->addDays(15)->toDateString();

    $this->artisan("silence:sweep --pretend-today={$future}")->assertSuccessful();

    $row = SilenceShadowLog::query()->sole();
    expect($row->is_pretend)->toBeTrue();
    expect($row->pretend_today?->toDateString())->toBe($future);
    // At +15 days the clock that was 5 days old becomes 20 days old →
    // landlord-side expired (>14) → send_escalation.
    expect($row->intended_action)->toBe('send_escalation');
});

it('--pretend-today is refused in production environment', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'production');
    sweepCaseLandlord();

    $this->artisan('silence:sweep --pretend-today=2026-12-01')->assertFailed();

    expect(SilenceShadowLog::query()->count())->toBe(0);
});

it('--pretend-today rejects malformed dates', function () {
    Mail::fake();
    app()->detectEnvironment(fn () => 'local');
    sweepCaseLandlord();

    $this->artisan('silence:sweep --pretend-today=not-a-date')->assertFailed();

    expect(SilenceShadowLog::query()->count())->toBe(0);
});

it('no_action rows are kept (per silence-phase-2a D0.2 — completeness over bloat)', function () {
    Mail::fake();
    sweepCaseLandlord(daysAgo: 5);   // clock not expired → no_action

    $this->artisan('silence:sweep')->assertSuccessful();

    $row = SilenceShadowLog::query()->sole();
    expect($row->intended_action)->toBe('no_action');
    expect($row->silence_days)->toBe(5);
    expect($row->ball_with)->toBe('landlord');
});

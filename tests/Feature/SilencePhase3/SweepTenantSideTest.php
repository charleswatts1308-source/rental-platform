<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\SilenceShadowLog;
use App\Services\Silence\SilenceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function phase3TenantSideCase(int $silenceDaysAgo): RepairCase
{
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->withLandlord([])->create([
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingTenantReview,
        'description' => 'Damp.',
        'ball_with' => 'tenant',
        'silence_clock_started_at' => Carbon::now()->subDays($silenceDaysAgo),
        'silence_settings_snapshot' => SilenceClock::snapshotCurrentSettings(),
    ]);
    CaseMessage::factory()->inbound()->create(['case_id' => $case->id]);

    return $case;
}

it('fires nudge 1 live at silence_days >= nudge.first_days (10)', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 11);

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertQueued(AutoEscalationTenantNotice::class);
    expect($case->events()->where('event_type', 'nudge_sent')->count())->toBe(1);
    expect($case->messages()->where('direction', MessageDirection::Outbound)->count())->toBe(0);
});

it('does NOT restart the silence clock on nudge — clock keeps accumulating (Correction 1)', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 11);
    $clockBefore = $case->silence_clock_started_at->copy();

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->fresh()->silence_clock_started_at->equalTo($clockBefore))->toBeTrue();
});

it('does not re-fire nudge 1 on the same day (idempotency via nudge_sent count)', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 11);

    $this->artisan('silence:sweep')->assertSuccessful();
    Mail::fake(); // clear queued count
    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertNothingQueued();
    expect($case->events()->where('event_type', 'nudge_sent')->count())->toBe(1);
});

it('fires nudge 2 when count=1 and silence >= nudge.second_days (20)', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 21);
    $case->events()->create([
        'event_type' => 'nudge_sent',
        'actor_label' => 'system',
        'occurred_at' => now()->subDays(10),
        'meta' => ['nudge_number' => 1],
    ]);

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->events()->where('event_type', 'nudge_sent')->count())->toBe(2);
    $latest = $case->events()
        ->where('event_type', 'nudge_sent')
        ->orderByDesc('id')
        ->first();
    expect($latest->meta['nudge_number'] ?? null)->toBe(2);
});

it('sends ONE nudge per sweep — count=0 at silence=21 fires nudge 1 only, not 1+2', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 21);

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->events()->where('event_type', 'nudge_sent')->count())->toBe(1);
    $latest = $case->events()->where('event_type', 'nudge_sent')->sole();
    expect($latest->meta['nudge_number'] ?? null)->toBe(1);
});

// ─── Ladder-walk dormancy (D2 explained-recoverable-sequence) ────

it('does NOT transition dormant at silence>=30 when count=0 — fires nudge 1 instead', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 35);

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($case->dormant_at)->toBeNull();
    expect($case->events()->where('event_type', 'nudge_sent')->count())->toBe(1);
    $latest = $case->events()->where('event_type', 'nudge_sent')->sole();
    expect($latest->meta['nudge_number'] ?? null)->toBe(1);
});

it('does NOT transition dormant at silence>=30 when count=1 — fires nudge 2 instead', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 35);
    $case->events()->create([
        'event_type' => 'nudge_sent',
        'actor_label' => 'system',
        'occurred_at' => now()->subDays(20),
        'meta' => ['nudge_number' => 1],
    ]);

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($case->dormant_at)->toBeNull();
    $latest = $case->events()
        ->where('event_type', 'nudge_sent')
        ->orderByDesc('id')
        ->first();
    expect($latest->meta['nudge_number'] ?? null)->toBe(2);
});

it('transitions dormant at silence>=30 ONLY when both nudges have been sent (count>=2)', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 35);
    foreach ([1, 2] as $n) {
        $case->events()->create([
            'event_type' => 'nudge_sent',
            'actor_label' => 'system',
            'occurred_at' => now()->subDays(35 - ($n * 5)),
            'meta' => ['nudge_number' => $n],
        ]);
    }

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::Dormant);
    expect($case->dormant_at)->not->toBeNull();
});

it('dormant transition fires the dormancy_transition_notice mail', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 35);
    foreach ([1, 2] as $n) {
        $case->events()->create([
            'event_type' => 'nudge_sent',
            'actor_label' => 'system',
            'occurred_at' => now()->subDays(35 - ($n * 5)),
            'meta' => ['nudge_number' => $n],
        ]);
    }

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertQueued(AutoEscalationTenantNotice::class);
});

it('excludes dormant cases from the sweep entirely (terminal for sweep)', function () {
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->withLandlord([])->create([
        'category_key' => $category->key,
        'description' => 'Damp.',
        'status' => CaseStatus::Dormant,
        'dormant_at' => now()->subDays(5),
    ]);

    $this->artisan('silence:sweep')->assertSuccessful();

    expect(SilenceShadowLog::where('case_id', $case->id)->count())->toBe(0);
});

// ─── Hold-expiry absorption (ResumeFromHold) ─────────────────────

it('resumes an OnHold case with past hold_until to AwaitingLandlord', function () {
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->withLandlord([])->create([
        'category_key' => $category->key,
        'description' => 'Damp.',
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->subDay(),
    ]);

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($case->ball_with)->toBe('landlord');
    expect($case->silence_clock_started_at)->not->toBeNull();
    Mail::assertQueued(AutoEscalationTenantNotice::class);
});

it('leaves an OnHold case with future hold_until alone', function () {
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->withLandlord([])->create([
        'category_key' => $category->key,
        'description' => 'Damp.',
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->addDays(7),
    ]);

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::OnHold);
});

// ─── Pretend forces full shadow ──────────────────────────────────

it('pretend mode executes nothing on tenant-side either', function () {
    app()->detectEnvironment(fn () => 'local');
    phase3TenantSideCase(silenceDaysAgo: 31);

    $this->artisan('silence:sweep', ['--pretend-today' => now()->toDateString()])->assertSuccessful();

    Mail::assertNothingQueued();
});

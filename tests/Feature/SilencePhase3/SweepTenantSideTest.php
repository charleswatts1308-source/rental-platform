<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\CaseMessage;
use App\Models\LandlordContact;
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
    $contact = LandlordContact::factory()->create();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
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

it('catches up nudges 1 and 2 in a single sweep run when both thresholds passed', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 21);

    $this->artisan('silence:sweep')->assertSuccessful();

    expect($case->events()->where('event_type', 'nudge_sent')->count())->toBe(2);
    $nudgeNumbers = $case->events()
        ->where('event_type', 'nudge_sent')
        ->pluck('meta')
        ->map(fn ($m) => $m['nudge_number'] ?? null)
        ->all();
    expect($nudgeNumbers)->toContain(1);
    expect($nudgeNumbers)->toContain(2);
});

it('transitions to dormant at silence_days >= nudge.dormancy_days (30)', function () {
    $case = phase3TenantSideCase(silenceDaysAgo: 31);

    $this->artisan('silence:sweep')->assertSuccessful();

    $case->refresh();
    expect($case->status)->toBe(CaseStatus::Dormant);
    expect($case->dormant_at)->not->toBeNull();
});

it('dormant transition fires the dormancy_transition_notice mail', function () {
    phase3TenantSideCase(silenceDaysAgo: 31);

    $this->artisan('silence:sweep')->assertSuccessful();

    Mail::assertQueued(AutoEscalationTenantNotice::class);
});

it('excludes dormant cases from the sweep entirely (terminal for sweep)', function () {
    $contact = LandlordContact::factory()->create();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
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
    $contact = LandlordContact::factory()->create();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
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
    $contact = LandlordContact::factory()->create();
    $category = RepairCategory::factory()->create();
    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
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

<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Helper: put a case into tenant_action_required and seed an entry event
 * (escalation_eligible) at the current frozen time. Tests then travel
 * forward to exercise the sweep at different elapsed-day points.
 */
function tarCaseEnteredNow(): RepairCase
{
    $case = RepairCase::factory()->create(['status' => CaseStatus::TenantActionRequired]);
    $case->events()->create([
        'event_type' => 'escalation_eligible',
        'actor_label' => 'system',
        'occurred_at' => now(),
    ]);

    return $case;
}

it('sends a 7-day reminder event after 7 days', function () {
    $case = tarCaseEnteredNow();

    $this->travel(7)->days();
    $this->artisan('cases:sweep-dormancy')->assertSuccessful();

    $reminders = $case->fresh()->events()
        ->where('event_type', 'dormancy_reminder_sent')
        ->get();

    expect($reminders)->toHaveCount(1);
    expect($reminders->first()->meta['day_offset'])->toBe(7);
});

it('does not send a 7-day reminder before 7 days', function () {
    $case = tarCaseEnteredNow();

    $this->travel(6)->days();
    $this->artisan('cases:sweep-dormancy')->assertSuccessful();

    expect($case->fresh()->events()->where('event_type', 'dormancy_reminder_sent')->count())
        ->toBe(0);
});

it('sends both 7-day and 14-day reminders by day 14', function () {
    $case = tarCaseEnteredNow();

    $this->travel(7)->days();
    $this->artisan('cases:sweep-dormancy');

    $this->travel(7)->days();
    $this->artisan('cases:sweep-dormancy');

    $offsets = $case->fresh()->events()
        ->where('event_type', 'dormancy_reminder_sent')
        ->orderBy('id')
        ->pluck('meta')
        ->map(fn ($m) => $m['day_offset'])
        ->all();

    expect($offsets)->toBe([7, 14]);
});

it('does not re-send the 7-day reminder on subsequent runs', function () {
    $case = tarCaseEnteredNow();

    $this->travel(8)->days();
    $this->artisan('cases:sweep-dormancy');
    $this->artisan('cases:sweep-dormancy');
    $this->artisan('cases:sweep-dormancy');

    $sevenDayReminders = $case->fresh()->events()
        ->where('event_type', 'dormancy_reminder_sent')
        ->where('meta->day_offset', 7)
        ->count();

    expect($sevenDayReminders)->toBe(1);
});

it('marks a case dormant after 21 days and writes case_dormant', function () {
    $case = tarCaseEnteredNow();

    $this->travel(21)->days();
    $this->artisan('cases:sweep-dormancy')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::Dormant);
    expect($case->fresh()->events()->where('event_type', 'case_dormant')->count())->toBe(1);
});

it('does not mark a case dormant before 21 days', function () {
    $case = tarCaseEnteredNow();

    $this->travel(20)->days();
    $this->artisan('cases:sweep-dormancy')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('is idempotent: re-running after dormancy keeps status dormant with one case_dormant event', function () {
    $case = tarCaseEnteredNow();

    $this->travel(21)->days();
    $this->artisan('cases:sweep-dormancy');
    $this->artisan('cases:sweep-dormancy');

    expect($case->fresh()->status)->toBe(CaseStatus::Dormant);
    expect($case->fresh()->events()->where('event_type', 'case_dormant')->count())->toBe(1);
});

it('does not pick up cases in other states', function () {
    $awaitingLandlord = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);
    $awaitingReview = RepairCase::factory()->create(['status' => CaseStatus::AwaitingTenantReview]);
    $onHold = RepairCase::factory()->create(['status' => CaseStatus::OnHold]);
    $resolved = RepairCase::factory()->create(['status' => CaseStatus::Resolved]);

    $this->travel(30)->days();
    $this->artisan('cases:sweep-dormancy')->assertSuccessful();

    expect($awaitingLandlord->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($awaitingReview->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($onHold->fresh()->status)->toBe(CaseStatus::OnHold);
    expect($resolved->fresh()->status)->toBe(CaseStatus::Resolved);
});

it('dormant cases are not re-processed by the sweep on subsequent runs', function () {
    $case = tarCaseEnteredNow();

    $this->travel(21)->days();
    $this->artisan('cases:sweep-dormancy');

    $this->travel(7)->days();
    $this->artisan('cases:sweep-dormancy');

    expect($case->fresh()->events()->where('event_type', 'dormancy_reminder_sent')->count())
        ->toBe(0);
    expect($case->fresh()->status)->toBe(CaseStatus::Dormant);
});

it('resets the dormancy clock when a case re-enters tenant_action_required from dormant', function () {
    $case = tarCaseEnteredNow();

    $this->travel(21)->days();
    $this->artisan('cases:sweep-dormancy');
    expect($case->fresh()->status)->toBe(CaseStatus::Dormant);

    $case->fresh()->transitionTo(CaseStatus::TenantActionRequired);

    $this->travel(6)->days();
    $this->artisan('cases:sweep-dormancy');

    $remindersSinceReEngage = $case->fresh()->events()
        ->where('event_type', 'dormancy_reminder_sent')
        ->where('occurred_at', '>=', now()->subDays(6))
        ->count();

    expect($remindersSinceReEngage)->toBe(0);
    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('uses the most recent entry event when a case has bounced through TAR multiple times', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::TenantActionRequired]);

    $case->events()->create([
        'event_type' => 'escalation_eligible',
        'actor_label' => 'system',
        'occurred_at' => now()->subDays(30),
    ]);
    $case->events()->create([
        'event_type' => 'hold_expired',
        'actor_label' => 'system',
        'occurred_at' => now()->subDays(2),
    ]);

    $this->artisan('cases:sweep-dormancy')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
    expect($case->fresh()->events()->where('event_type', 'dormancy_reminder_sent')->count())->toBe(0);
    expect($case->fresh()->events()->where('event_type', 'case_dormant')->count())->toBe(0);
});

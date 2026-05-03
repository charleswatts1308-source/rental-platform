<?php

use App\Enums\CaseStatus;
use App\Mail\Notifications\DormancyReminder;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

function tarCaseForReminders(): RepairCase
{
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::TenantActionRequired,
    ]);
    $case->events()->create([
        'event_type' => 'escalation_eligible',
        'actor_label' => 'system',
        'occurred_at' => now(),
    ]);

    return $case;
}

it('queues a DormancyReminder with day_offset 7 on the first reminder', function () {
    $case = tarCaseForReminders();

    $this->travel(7)->days();
    $this->artisan('cases:sweep-dormancy');

    Mail::assertQueued(DormancyReminder::class, function (DormancyReminder $mail) use ($case) {
        return $mail->case->is($case) && $mail->dayOffset === 7;
    });
});

it('queues a DormancyReminder with day_offset 14 alongside the day_offset 7 catch-up', function () {
    tarCaseForReminders();

    $this->travel(14)->days();
    $this->artisan('cases:sweep-dormancy');

    Mail::assertQueued(DormancyReminder::class, fn (DormancyReminder $m) => $m->dayOffset === 7);
    Mail::assertQueued(DormancyReminder::class, fn (DormancyReminder $m) => $m->dayOffset === 14);
});

it('does not re-queue a DormancyReminder that has already been sent', function () {
    tarCaseForReminders();

    $this->travel(8)->days();
    $this->artisan('cases:sweep-dormancy');
    $this->artisan('cases:sweep-dormancy');

    Mail::assertQueuedCount(1);
});

it('does not queue a reminder when the case has been marked dormant in the same run', function () {
    tarCaseForReminders();

    $this->travel(21)->days();
    $this->artisan('cases:sweep-dormancy');

    Mail::assertNothingQueued();
});

it('renders a privacy-safe subject', function () {
    $case = RepairCase::factory()->create();
    $envelope = (new DormancyReminder($case, 7))->envelope();

    expect($envelope->subject)->toBe('A reminder about your repair case');
    expect($envelope->subject)->not->toContain('@');
});

it('renders the body with a deep link to the case and tailors phrasing per offset', function () {
    $case = RepairCase::factory()->create();

    $atSeven = (new DormancyReminder($case, 7))->render();
    $atFourteen = (new DormancyReminder($case, 14))->render();

    expect($atSeven)->toContain(route('cases.show', $case->url_slug));
    expect($atSeven)->toContain('a week');
    expect($atFourteen)->toContain('two weeks');
});

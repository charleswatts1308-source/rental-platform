<?php

use App\Enums\CaseStatus;
use App\Mail\Notifications\EscalationEligible;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('queues an EscalationEligible notification when SweepEscalations transitions a case', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-escalations');

    Mail::assertQueued(EscalationEligible::class, function (EscalationEligible $mail) use ($tenant, $case) {
        return $mail->hasTo($tenant->email) && $mail->case->is($case);
    });
});

it('does not queue a notification when no case meets the escalation criteria', function () {
    RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->addDay(),
    ]);

    $this->artisan('cases:sweep-escalations');

    Mail::assertNothingQueued();
});

it('queues exactly one notification per case (idempotent across re-runs)', function () {
    $tenant = User::factory()->create();
    RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-escalations');
    $this->artisan('cases:sweep-escalations');

    Mail::assertQueuedCount(1);
});

it('renders a privacy-safe subject', function () {
    $case = RepairCase::factory()->create();
    $envelope = (new EscalationEligible($case))->envelope();

    expect($envelope->subject)->toBe('Your repair case is ready for the next step');
    expect($envelope->subject)->not->toContain('@');
});

it('renders the body with a deep link to the case', function () {
    $case = RepairCase::factory()->create();

    $rendered = (new EscalationEligible($case))->render();

    expect($rendered)->toContain(route('cases.show', $case->url_slug));
});

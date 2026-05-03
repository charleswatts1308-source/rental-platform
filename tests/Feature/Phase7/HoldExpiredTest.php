<?php

use App\Enums\CaseStatus;
use App\Mail\Notifications\HoldExpired;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('queues a HoldExpired notification when SweepHolds releases a case', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-holds');

    Mail::assertQueued(HoldExpired::class, function (HoldExpired $mail) use ($tenant, $case) {
        return $mail->hasTo($tenant->email) && $mail->case->is($case);
    });
});

it('does not queue a notification when no on_hold case has expired', function () {
    RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->addDay(),
    ]);

    $this->artisan('cases:sweep-holds');

    Mail::assertNothingQueued();
});

it('queues exactly one notification per released hold (idempotent across re-runs)', function () {
    RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-holds');
    $this->artisan('cases:sweep-holds');

    Mail::assertQueuedCount(1);
});

it('renders a privacy-safe subject', function () {
    $case = RepairCase::factory()->create();
    $envelope = (new HoldExpired($case))->envelope();

    expect($envelope->subject)->toBe('The hold on your repair case has ended');
    expect($envelope->subject)->not->toContain('@');
});

it('renders the body with a deep link to the case', function () {
    $case = RepairCase::factory()->create();

    $rendered = (new HoldExpired($case))->render();

    expect($rendered)->toContain(route('cases.show', $case->url_slug));
});

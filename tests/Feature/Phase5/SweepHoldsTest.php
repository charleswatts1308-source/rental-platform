<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('releases on_hold cases past their hold_until', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-holds')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('writes hold_expired as the canonical event for released holds', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-holds')->assertSuccessful();

    expect($case->fresh()->events()->orderByDesc('id')->first()->event_type)
        ->toBe('hold_expired');
});

it('preserves hold_until as historical record after transition', function () {
    $hold = now()->subDay()->startOfMinute();
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => $hold,
    ]);

    $this->artisan('cases:sweep-holds')->assertSuccessful();

    expect($case->fresh()->hold_until->equalTo($hold))->toBeTrue();
});

it('leaves on_hold cases whose hold_until is in the future alone', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->addDays(3),
    ]);

    $this->artisan('cases:sweep-holds')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::OnHold);
});

it('does not pick up cases in other states even if hold_until has elapsed', function () {
    $awaiting = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'hold_until' => now()->subDay(),
    ]);
    $review = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingTenantReview,
        'hold_until' => now()->subDay(),
    ]);

    $this->artisan('cases:sweep-holds')->assertSuccessful();

    expect($awaiting->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
    expect($review->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
});

it('is idempotent: running twice in one day produces one transition, not two', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-holds')->assertSuccessful();
    $this->artisan('cases:sweep-holds')->assertSuccessful();

    $events = $case->fresh()->events()
        ->where('event_type', 'hold_expired')
        ->count();

    expect($events)->toBe(1);
    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('processes multiple expired holds in a single run', function () {
    RepairCase::factory()->count(3)->create([
        'status' => CaseStatus::OnHold,
        'hold_until' => now()->subHour(),
    ]);

    $this->artisan('cases:sweep-holds')->assertSuccessful();

    expect(RepairCase::where('status', CaseStatus::TenantActionRequired)->count())->toBe(3);
});

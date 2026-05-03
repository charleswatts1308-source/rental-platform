<?php

use App\Enums\CaseStatus;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('transitions awaiting_landlord cases past their next_stage_eligible_at', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-escalations')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('writes the canonical escalation_eligible event for transitioned cases', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-escalations')->assertSuccessful();

    expect($case->fresh()->events()->orderByDesc('id')->first()->event_type)
        ->toBe('escalation_eligible');
});

it('leaves cases whose next_stage_eligible_at is in the future alone', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->addDay(),
    ]);

    $this->artisan('cases:sweep-escalations')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('leaves cases with null next_stage_eligible_at alone (e.g. stage 4, no further escalations)', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => null,
    ]);

    $this->artisan('cases:sweep-escalations')->assertSuccessful();

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('does not pick up cases in other states even if next_stage_eligible_at has elapsed', function () {
    $onHold = RepairCase::factory()->create([
        'status' => CaseStatus::OnHold,
        'next_stage_eligible_at' => now()->subDay(),
        'hold_until' => now()->addDay(),
    ]);
    $review = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingTenantReview,
        'next_stage_eligible_at' => now()->subDay(),
    ]);
    $tar = RepairCase::factory()->create([
        'status' => CaseStatus::TenantActionRequired,
        'next_stage_eligible_at' => now()->subDay(),
    ]);

    $this->artisan('cases:sweep-escalations')->assertSuccessful();

    expect($onHold->fresh()->status)->toBe(CaseStatus::OnHold);
    expect($review->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($tar->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('is idempotent: running twice in one day produces one transition, not two', function () {
    $case = RepairCase::factory()->create([
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->subMinute(),
    ]);

    $this->artisan('cases:sweep-escalations')->assertSuccessful();
    $this->artisan('cases:sweep-escalations')->assertSuccessful();

    $escalations = $case->fresh()->events()
        ->where('event_type', 'escalation_eligible')
        ->count();

    expect($escalations)->toBe(1);
    expect($case->fresh()->status)->toBe(CaseStatus::TenantActionRequired);
});

it('processes multiple eligible cases in a single run', function () {
    RepairCase::factory()->count(3)->create([
        'status' => CaseStatus::AwaitingLandlord,
        'next_stage_eligible_at' => now()->subHour(),
    ]);

    $this->artisan('cases:sweep-escalations')->assertSuccessful();

    expect(RepairCase::where('status', CaseStatus::TenantActionRequired)->count())->toBe(3);
});

<?php

use App\Enums\CaseStatus;
use App\Models\CaseEvent;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('transitions to the target status and writes the canonical event', function (
    CaseStatus $from,
    CaseStatus $to,
    string $expectedEventType,
) {
    $case = RepairCase::factory()->create(['status' => $from]);
    $eventCountBefore = CaseEvent::where('case_id', $case->id)->count();

    $case->transitionTo($to);

    expect($case->fresh()->status)->toBe($to);

    $latestEvent = $case->events()->orderByDesc('id')->first();
    expect($latestEvent->event_type)->toBe($expectedEventType);
    expect($latestEvent->actor_label)->toBe('system');
    expect(CaseEvent::where('case_id', $case->id)->count())->toBe($eventCountBefore + 1);
})->with([
    'open → awaiting_landlord' => [CaseStatus::Open, CaseStatus::AwaitingLandlord, 'notice_sent'],
    'awaiting_landlord → awaiting_tenant_review' => [CaseStatus::AwaitingLandlord, CaseStatus::AwaitingTenantReview, 'inbound_received'],
    'awaiting_landlord → resolved' => [CaseStatus::AwaitingLandlord, CaseStatus::Resolved, 'case_resolved'],
    'awaiting_landlord → abandoned' => [CaseStatus::AwaitingLandlord, CaseStatus::Abandoned, 'case_abandoned'],
    // Phase 3 D8 — tenant reply: awaiting_tenant_review → awaiting_landlord
    'awaiting_tenant_review → awaiting_landlord (tenant_replied)' => [CaseStatus::AwaitingTenantReview, CaseStatus::AwaitingLandlord, 'tenant_replied'],
    'awaiting_tenant_review → on_hold' => [CaseStatus::AwaitingTenantReview, CaseStatus::OnHold, 'hold_set'],
    'awaiting_tenant_review → resolved' => [CaseStatus::AwaitingTenantReview, CaseStatus::Resolved, 'case_resolved'],
    'awaiting_tenant_review → abandoned' => [CaseStatus::AwaitingTenantReview, CaseStatus::Abandoned, 'case_abandoned'],
    // Phase 3 — dormancy from awaiting_tenant_review (silence sweep transition).
    'awaiting_tenant_review → dormant' => [CaseStatus::AwaitingTenantReview, CaseStatus::Dormant, 'case_dormant'],
    // Phase 3 — hold expiry absorbed: OnHold → AwaitingLandlord (with event_type_override 'hold_expired' applied by the sweep).
    'on_hold → awaiting_landlord (tenant_replied default)' => [CaseStatus::OnHold, CaseStatus::AwaitingLandlord, 'tenant_replied'],
    'on_hold → awaiting_tenant_review' => [CaseStatus::OnHold, CaseStatus::AwaitingTenantReview, 'inbound_received'],
    'on_hold → resolved' => [CaseStatus::OnHold, CaseStatus::Resolved, 'case_resolved'],
    'on_hold → abandoned' => [CaseStatus::OnHold, CaseStatus::Abandoned, 'case_abandoned'],
    // Phase 3 D8 — dormant revival via tenant reply.
    'dormant → awaiting_landlord (tenant_replied)' => [CaseStatus::Dormant, CaseStatus::AwaitingLandlord, 'tenant_replied'],
    // Phase 3 D0.3 — direct resolve from dormant ("it got fixed while I was away").
    'dormant → resolved' => [CaseStatus::Dormant, CaseStatus::Resolved, 'case_resolved'],
    'dormant → abandoned' => [CaseStatus::Dormant, CaseStatus::Abandoned, 'case_abandoned'],
]);

it('records the actor_label from context when provided', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->transitionTo(CaseStatus::Resolved, ['actor_label' => 'tenant']);

    expect($case->events()->orderByDesc('id')->first()->actor_label)->toBe('tenant');
});

it('records the actor_user_id from context when provided', function () {
    $tenant = User::factory()->create();
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->transitionTo(CaseStatus::Resolved, [
        'actor_label' => 'tenant',
        'actor_user_id' => $tenant->id,
    ]);

    expect($case->events()->orderByDesc('id')->first()->actor_user_id)->toBe($tenant->id);
});

it('records meta from context when provided', function () {
    $case = RepairCase::factory()->create(['status' => CaseStatus::AwaitingLandlord]);

    $case->transitionTo(CaseStatus::Abandoned, [
        'actor_label' => 'tenant',
        'meta' => ['reason' => 'tenant_moved_out'],
    ]);

    expect($case->events()->orderByDesc('id')->first()->meta)->toBe([
        'reason' => 'tenant_moved_out',
    ]);
});

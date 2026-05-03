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
    'awaiting_landlord → tenant_action_required' => [CaseStatus::AwaitingLandlord, CaseStatus::TenantActionRequired, 'escalation_eligible'],
    'awaiting_landlord → resolved' => [CaseStatus::AwaitingLandlord, CaseStatus::Resolved, 'case_resolved'],
    'awaiting_landlord → abandoned' => [CaseStatus::AwaitingLandlord, CaseStatus::Abandoned, 'case_abandoned'],
    'awaiting_tenant_review → tenant_action_required' => [CaseStatus::AwaitingTenantReview, CaseStatus::TenantActionRequired, 'escalation_eligible'],
    'awaiting_tenant_review → on_hold' => [CaseStatus::AwaitingTenantReview, CaseStatus::OnHold, 'hold_set'],
    'awaiting_tenant_review → resolved' => [CaseStatus::AwaitingTenantReview, CaseStatus::Resolved, 'case_resolved'],
    'awaiting_tenant_review → abandoned' => [CaseStatus::AwaitingTenantReview, CaseStatus::Abandoned, 'case_abandoned'],
    'tenant_action_required → awaiting_landlord' => [CaseStatus::TenantActionRequired, CaseStatus::AwaitingLandlord, 'stage_advanced'],
    'tenant_action_required → on_hold' => [CaseStatus::TenantActionRequired, CaseStatus::OnHold, 'hold_set'],
    'tenant_action_required → resolved' => [CaseStatus::TenantActionRequired, CaseStatus::Resolved, 'case_resolved'],
    'tenant_action_required → abandoned' => [CaseStatus::TenantActionRequired, CaseStatus::Abandoned, 'case_abandoned'],
    'tenant_action_required → dormant' => [CaseStatus::TenantActionRequired, CaseStatus::Dormant, 'case_dormant'],
    'on_hold → tenant_action_required' => [CaseStatus::OnHold, CaseStatus::TenantActionRequired, 'hold_expired'],
    'on_hold → awaiting_tenant_review' => [CaseStatus::OnHold, CaseStatus::AwaitingTenantReview, 'inbound_received'],
    'on_hold → resolved' => [CaseStatus::OnHold, CaseStatus::Resolved, 'case_resolved'],
    'on_hold → abandoned' => [CaseStatus::OnHold, CaseStatus::Abandoned, 'case_abandoned'],
    'dormant → tenant_action_required' => [CaseStatus::Dormant, CaseStatus::TenantActionRequired, 'tenant_re_engaged'],
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

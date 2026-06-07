<?php

use App\Enums\BallPosition;
use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Services\Silence\SilenceClock;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Turn detection: pure message-direction rule (silence-phase-2a ruling
 * a). Whoever did NOT send the latest case_messages row holds the ball.
 * Status acts as a no-clock veto for Open/OnHold/Resolved/Abandoned/
 * Dormant.
 *
 * Critical coverage: tenant_action_required has two entry paths under
 * the old model — escalation-eligible (last message = outbound notice
 * → ball with landlord) and hold-expired (last message = inbound reply
 * → ball with tenant). Both are exercised separately.
 */
function caseInStatus(CaseStatus $status): RepairCase
{
    return RepairCase::factory()->create([
        'status' => $status,
        'silence_clock_started_at' => null,
        'silence_settings_snapshot' => null,
    ]);
}

function withLatestOutbound(RepairCase $case): RepairCase
{
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => 1,
    ]);

    return $case;
}

function withLatestInbound(RepairCase $case): RepairCase
{
    CaseMessage::factory()->inbound()->create([
        'case_id' => $case->id,
    ]);

    return $case;
}

it('open: no clock — no messages exist yet', function () {
    $case = caseInStatus(CaseStatus::Open);
    expect((new SilenceClock)->ballFor($case))->toBeNull();
});

it('on_hold: no clock — D2 explicit pause', function () {
    $case = withLatestInbound(caseInStatus(CaseStatus::OnHold));
    expect((new SilenceClock)->ballFor($case))->toBeNull();
});

it('resolved: no clock — terminal', function () {
    $case = withLatestOutbound(caseInStatus(CaseStatus::Resolved));
    expect((new SilenceClock)->ballFor($case))->toBeNull();
});

it('abandoned: no clock — terminal', function () {
    $case = withLatestOutbound(caseInStatus(CaseStatus::Abandoned));
    expect((new SilenceClock)->ballFor($case))->toBeNull();
});

it('dormant: no clock — end of nudge ladder', function () {
    $case = withLatestOutbound(caseInStatus(CaseStatus::Dormant));
    expect((new SilenceClock)->ballFor($case))->toBeNull();
});

it('awaiting_landlord: ball with landlord — last message was outbound notice', function () {
    $case = withLatestOutbound(caseInStatus(CaseStatus::AwaitingLandlord));
    expect((new SilenceClock)->ballFor($case))->toBe(BallPosition::Landlord);
});

it('awaiting_tenant_review: ball with tenant — last message was inbound landlord reply', function () {
    $case = withLatestInbound(caseInStatus(CaseStatus::AwaitingTenantReview));
    expect((new SilenceClock)->ballFor($case))->toBe(BallPosition::Tenant);
});

// Phase 3 — tenant_action_required state demolished (D0.3 Option A).
// Both "TAR via escalation path" and "TAR via hold-expiry path" tests
// dropped — the source state no longer exists. The remaining ball-
// detection cases below cover the surviving statuses.

it('quarantined inbound still flips the ball — the landlord engaged', function () {
    $case = caseInStatus(CaseStatus::AwaitingTenantReview);
    CaseMessage::factory()->inbound()->create([
        'case_id' => $case->id,
        'quarantine_reason' => 'unexpected_from_address',
    ]);

    expect((new SilenceClock)->ballFor($case))->toBe(BallPosition::Tenant);
});

it('latest message wins — ball position tracks the most recent direction', function () {
    $case = caseInStatus(CaseStatus::AwaitingTenantReview);
    // First an outbound (ball=landlord), then an inbound (ball=tenant).
    // The latest message is what counts.
    CaseMessage::factory()->create([
        'case_id' => $case->id,
        'direction' => MessageDirection::Outbound,
        'sender_role' => SenderRole::System,
        'stage_at_send' => 1,
    ]);
    CaseMessage::factory()->inbound()->create([
        'case_id' => $case->id,
    ]);

    expect((new SilenceClock)->ballFor($case))->toBe(BallPosition::Tenant);
});

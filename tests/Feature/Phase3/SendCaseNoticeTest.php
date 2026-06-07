<?php

use App\Actions\SendCaseNotice;
use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\LandlordContact;
use App\Models\RepairCase;
use App\Models\RepairCategory;
use App\Models\ReplyToken;
use App\Services\LetterTemplateRenderer;
use App\Services\ReplyTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function sendCaseNoticeAction(): SendCaseNotice
{
    return new SendCaseNotice(new ReplyTokenGenerator, new LetterTemplateRenderer);
}

function makeOpenCase(): RepairCase
{
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com']);
    $category = RepairCategory::factory()->create();

    return RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'status' => CaseStatus::Open,
        'current_stage' => 1,
    ]);
}

/**
 * Phase 3 — auto-escalation case shape replaces the demolished
 * TAR-driven escalation. Status=AwaitingLandlord with an expired
 * silence clock + active token mimics the state the sweep finds when
 * firing send_escalation.
 */
function makeAwaitingLandlordCaseWithActiveToken(int $currentStage = 1): RepairCase
{
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com']);
    $category = RepairCategory::factory()->create();

    $case = RepairCase::factory()->create([
        'landlord_contact_id' => $contact->id,
        'category_key' => $category->key,
        'status' => CaseStatus::AwaitingLandlord,
        'current_stage' => $currentStage,
        'silence_clock_started_at' => now()->subDays(20),
        'silence_settings_snapshot' => \App\Services\Silence\SilenceClock::snapshotCurrentSettings(),
    ]);

    ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $contact->email,
        'superseded_at' => null,
    ]);

    return $case;
}

it('writes an outbound case_message with stage 1 fields on first send', function () {
    Mail::fake();
    $case = makeOpenCase();

    $message = sendCaseNoticeAction()->execute($case);

    expect($message)->toBeInstanceOf(CaseMessage::class);
    expect($message->direction)->toBe(MessageDirection::Outbound);
    expect($message->sender_role)->toBe(SenderRole::System);
    expect($message->stage_at_send)->toBe(1);
    expect($message->letter_template_id)->not->toBeNull();
    expect($message->body_raw)->toContain('Landlord and Tenant Act 1985');
    expect($message->subject)->toContain('Repair issue notice');
    expect($message->to_address_raw)->toBe('landlord@example.com');
    expect($message->sent_at)->toBeInstanceOf(Carbon::class);
});

it('mints a new active reply token on first send', function () {
    Mail::fake();
    $case = makeOpenCase();

    expect($case->replyTokens()->count())->toBe(0);

    sendCaseNoticeAction()->execute($case);

    expect($case->replyTokens()->count())->toBe(1);
    $token = $case->replyTokens()->sole();
    expect($token->superseded_at)->toBeNull();
    expect($token->bound_email)->toBe('landlord@example.com');
    expect(strlen($token->token))->toBe(20);
});

it('transitions an Open case to AwaitingLandlord on first send', function () {
    Mail::fake();
    $case = makeOpenCase();

    sendCaseNoticeAction()->execute($case);

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

// "sets next_stage_eligible_at 14 days out after stage 1" — DELETED per
// silence-phase-2b D0.1 #13. The column and its per-stage cadence are
// demolished; the silence model uses settings-driven intervals
// (covered by SilencePhase2a InflightGuardrailTest).

it('queues the CaseNotice mailable to the landlord contact', function () {
    Mail::fake();
    $case = makeOpenCase();

    sendCaseNoticeAction()->execute($case);

    Mail::assertQueued(CaseNotice::class, function ($mail) {
        return $mail->hasTo('landlord@example.com');
    });
});

it('writes notice_sent (via transitionTo) and token_issued (via action) on first send', function () {
    Mail::fake();
    $case = makeOpenCase();

    sendCaseNoticeAction()->execute($case);

    $eventTypes = $case->events()->pluck('event_type')->all();
    expect($eventTypes)->toContain('case_opened'); // from creation
    expect($eventTypes)->toContain('notice_sent');
    expect($eventTypes)->toContain('token_issued');
});

it('does not write token_superseded on first send', function () {
    Mail::fake();
    $case = makeOpenCase();

    sendCaseNoticeAction()->execute($case);

    $eventTypes = $case->events()->pluck('event_type')->all();
    // Phase 3 — escalation_confirmed_by_tenant demolished with the
    // tenant-click branch (D7 resolved).
    expect($eventTypes)->not->toContain('escalation_confirmed_by_tenant');
    expect($eventTypes)->not->toContain('token_superseded');
});

it('on auto-escalation: writes auto_escalation_sent + token_issued + token_superseded, NOT a transition', function () {
    Mail::fake();
    $case = makeAwaitingLandlordCaseWithActiveToken(currentStage: 1);

    sendCaseNoticeAction()->execute($case);

    $eventTypesAfter = $case->events()->orderBy('id')->pluck('event_type')->all();
    foreach (['auto_escalation_sent', 'token_issued', 'token_superseded'] as $expected) {
        expect($eventTypesAfter)->toContain($expected);
    }
    // Phase 3 — no stage_advanced (that was the tenant-click TAR
    // exit) and no escalation_confirmed_by_tenant (demolished).
    expect($eventTypesAfter)->not->toContain('stage_advanced');
    expect($eventTypesAfter)->not->toContain('escalation_confirmed_by_tenant');
});

it('on auto-escalation: supersedes the previous active token with 90-day expiry', function () {
    Mail::fake();
    $case = makeAwaitingLandlordCaseWithActiveToken(currentStage: 1);
    $oldToken = $case->replyTokens()->whereNull('superseded_at')->sole();

    Carbon::setTestNow('2026-06-01 10:00:00');
    sendCaseNoticeAction()->execute($case);
    Carbon::setTestNow();

    $oldToken->refresh();
    expect($oldToken->superseded_at)->not->toBeNull();
    expect($oldToken->expires_at->toDateString())->toBe('2026-08-30');
});

it('on auto-escalation: increments current_stage and uses the new stage in the message', function () {
    Mail::fake();
    $case = makeAwaitingLandlordCaseWithActiveToken(currentStage: 2);

    $message = sendCaseNoticeAction()->execute($case);

    expect($message->stage_at_send)->toBe(3);
    expect($case->fresh()->current_stage)->toBe(3);
});

// "on escalation: sets next_stage_eligible_at 21 days out after stage 3 send"
// — DELETED per silence-phase-2b D0.1 #14.
// "after stage 4 send: next_stage_eligible_at is null" — DELETED per
// silence-phase-2b D0.1 #15.

it('throws when called on a case in an unsupported status', function () {
    Mail::fake();
    $case = RepairCase::factory()->create(['status' => CaseStatus::Resolved]);

    sendCaseNoticeAction()->execute($case);
})->throws(LogicException::class);

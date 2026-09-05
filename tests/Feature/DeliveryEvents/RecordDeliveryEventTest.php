<?php

use App\Enums\CaseStatus;
use App\Mail\Notifications\AutoEscalationTenantNotice;
use App\Models\CaseEvent;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/**
 * #25 step 6 — the receiver writes the event into the case record.
 *
 * Step 7a added the reaction: a PERMANENT failure and a COMPLAINT stop
 * the case at contact_failed and notify the tenant mail-only. A temporary
 * failure and a delivery are recorded and nothing else happens. The
 * D17.3 tenant-taken copy is still step 7b.
 *
 * Payload shapes are from the three real production sends captured on
 * 23 Aug 2026 (docs/mailgun-delivery-event-payloads.md), including the
 * two that both arrive as failed/permanent and are told apart only by
 * `reason`.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key-shhh');
});

function signedEvent(array $eventData): array
{
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $token = str_repeat('b', 50);

    return [
        'signature' => [
            'token' => $token,
            'timestamp' => $timestamp,
            'signature' => hash_hmac('sha256', $timestamp.$token, $signingKey),
        ],
        'event-data' => $eventData,
    ];
}

function outboundMessage(CaseStatus $status = CaseStatus::AwaitingLandlord): CaseMessage
{
    $case = RepairCase::factory()->create(['status' => $status]);

    return CaseMessage::factory()->create(['case_id' => $case->id]);
}

/**
 * Send 1 of the capture run: a real bounce that WAS attempted.
 */
function failedEvent(CaseMessage $message, array $overrides = []): array
{
    return array_merge([
        'id' => 'mailgun-event-id-0001',
        'event' => 'failed',
        'severity' => 'permanent',
        'reason' => 'generic',
        'recipient' => 'landlord@this-domain-does-not-exist-9f3k2.com',
        'timestamp' => 1787507330,
        'user-variables' => ['case_message_id' => (string) $message->id],
    ], $overrides);
}

it('records a permanent failure against the right case', function () {
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message)))
        ->assertOk();

    $event = CaseEvent::where('event_type', 'delivery_failed')->sole();

    expect($event->case_id)->toBe($message->case_id)
        ->and($event->actor_label)->toBe('system')
        ->and($event->meta['case_message_id'])->toBe($message->id)
        ->and($event->meta['mailgun_event_id'])->toBe('mailgun-event-id-0001')
        ->and($event->meta['severity'])->toBe('permanent')
        ->and($event->meta['reason'])->toBe('generic')
        ->and($event->meta['recipient'])->toBe('landlord@this-domain-does-not-exist-9f3k2.com');
});

it('records a delivered event as delivery_confirmed', function () {
    // D17.7 / D17.9. Named for what it means: a mail server accepted the
    // message. It says nothing about anyone reading it.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'id' => 'mailgun-event-id-0003',
        'event' => 'delivered',
        'severity' => null,
        'reason' => null,
    ])))->assertOk();

    expect(CaseEvent::where('event_type', 'delivery_confirmed')->count())->toBe(1);
});

it('tells a real bounce apart from a suppressed-address drop', function (string $reason) {
    // BOTH arrive as failed/permanent. severity alone cannot separate
    // them; `reason` is the discriminator, and only suppress-bounce means
    // letters are being swallowed before any delivery attempt.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'reason' => $reason,
    ])))->assertOk();

    expect(CaseEvent::where('event_type', 'delivery_failed')->sole()->meta['reason'])
        ->toBe($reason);
})->with(['generic', 'suppress-bounce']);

it('records a TEMPORARY failure too — silent to the tenant, visible in the record', function () {
    // D17.9. D17.2 rules that a temporary failure produces no
    // tenant-facing action; that is about the tenant, not about the
    // evidence.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'severity' => 'temporary',
        'reason' => 'generic',
    ])))->assertOk();

    expect(CaseEvent::where('event_type', 'delivery_failed')->sole()->meta['severity'])
        ->toBe('temporary');
});

it('ignores a repeat of an event it has already recorded', function () {
    // D17.9 — Mailgun re-sends when unsure a webhook arrived. Without
    // this, one bounce writes two rows in the evidence record.
    $message = outboundMessage();
    $payload = signedEvent(failedEvent($message));

    $this->postJson('/webhooks/mailgun/events', $payload)->assertOk();
    $this->postJson('/webhooks/mailgun/events', $payload)->assertOk();

    expect(CaseEvent::where('event_type', 'delivery_failed')->count())->toBe(1);
});

it('records two DIFFERENT events on the same message', function () {
    // The dedupe keys on Mailgun's event id, not on the message — a
    // temporary failure followed by a delivery is a real sequence.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'id' => 'event-one', 'severity' => 'temporary',
    ])))->assertOk();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'id' => 'event-two', 'event' => 'delivered',
    ])))->assertOk();

    expect(CaseEvent::whereIn('event_type', ['delivery_failed', 'delivery_confirmed'])->count())
        ->toBe(2);
});

it('accepts an event it cannot match to a message, and writes nothing', function () {
    // D17.9 — a non-2xx would make Mailgun retry for hours against a
    // payload that cannot match on any attempt.
    $this->postJson('/webhooks/mailgun/events', signedEvent([
        'id' => 'orphan-event',
        'event' => 'failed',
        'severity' => 'permanent',
        'reason' => 'generic',
        'recipient' => 'nobody@example.com',
        'user-variables' => ['case_message_id' => '999999'],
    ]))->assertOk();

    expect(CaseEvent::whereIn('event_type', ['delivery_failed', 'delivery_confirmed'])->count())
        ->toBe(0);
});

it('accepts an event carrying no correlation variable at all', function () {
    $this->postJson('/webhooks/mailgun/events', signedEvent([
        'id' => 'no-vars',
        'event' => 'failed',
        'severity' => 'permanent',
        'recipient' => 'nobody@example.com',
    ]))->assertOk();

    expect(CaseEvent::count())->toBe(0);
});

it('accepts an event type outside the ruled set, and writes nothing', function (string $event) {
    // These should never arrive: we set no tracking options and no
    // unsubscribe headers on our sends. If one does, the log line is the
    // useful part — a Mailgun setting changed underneath us.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'event' => $event,
    ])))->assertOk();

    expect(CaseEvent::whereIn('event_type', ['delivery_failed', 'delivery_confirmed'])->count())
        ->toBe(0);
})->with(['opened', 'clicked', 'unsubscribed']);

it('accepts a body with no event-data at all', function () {
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $token = str_repeat('c', 50);

    $this->postJson('/webhooks/mailgun/events', [
        'signature' => [
            'token' => $token,
            'timestamp' => $timestamp,
            'signature' => hash_hmac('sha256', $timestamp.$token, $signingKey),
        ],
    ])->assertOk();

    expect(CaseEvent::count())->toBe(0);
});

it('refuses an unsigned event before the action ever runs', function () {
    $this->postJson('/webhooks/mailgun/events', [
        'event-data' => ['event' => 'failed', 'id' => 'unsigned'],
    ])->assertStatus(406);

    expect(CaseEvent::count())->toBe(0);
});

it('records a complaint — the strongest evidence of receipt there is', function () {
    // D17.5's asymmetry, and the reason this is recorded rather than
    // ignored: a bounce proves the letter went NOWHERE; a complaint proves
    // the opposite — it arrived, was seen, and was rejected. On a product
    // whose claim is "your landlord was served", that is not noise.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'id' => 'complaint-0001',
        'event' => 'complained',
        'severity' => null,
        'reason' => null,
        'recipient' => 'landlord@example.com',
    ])))->assertOk();

    $event = CaseEvent::where('event_type', 'delivery_complained')->sole();

    expect($event->case_id)->toBe($message->case_id)
        ->and($event->meta['case_message_id'])->toBe($message->id)
        ->and($event->meta['mailgun_event_id'])->toBe('complaint-0001')
        ->and($event->meta['recipient'])->toBe('landlord@example.com');
});

it('does not record the same complaint twice', function () {
    $message = outboundMessage();
    $payload = signedEvent(failedEvent($message, [
        'id' => 'complaint-0002',
        'event' => 'complained',
    ]));

    $this->postJson('/webhooks/mailgun/events', $payload)->assertOk();
    $this->postJson('/webhooks/mailgun/events', $payload)->assertOk();

    expect(CaseEvent::where('event_type', 'delivery_complained')->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Step 7a — what a failure DOES
|--------------------------------------------------------------------------
|
| The step-6 tests asserting no status change are REPLACED here rather
| than relaxed. What they pinned was correct for step 6 and is
| deliberately different now.
|
*/

it('stops the case on a permanent failure', function () {
    // D17.2 — a bounce is not a variant of silence, it is the opposite.
    // Silence escalates; this must stop the ladder.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message)))
        ->assertOk();

    expect($message->case->fresh()->status)->toBe(CaseStatus::ContactFailed);
});

it('stops the case on a complaint, wherever it occurs', function (CaseStatus $from) {
    // D17.5 — terminal wherever it occurs, and it never forks.
    $message = outboundMessage($from);

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'event' => 'complained', 'severity' => null,
    ])))->assertOk();

    expect($message->case->fresh()->status)->toBe(CaseStatus::ContactFailed);
})->with([
    'awaiting_landlord' => [CaseStatus::AwaitingLandlord],
    'awaiting_tenant_review' => [CaseStatus::AwaitingTenantReview],
    'on_hold' => [CaseStatus::OnHold],
    'dormant' => [CaseStatus::Dormant],
]);

it('does NOT stop the case on a temporary failure', function () {
    // D17.2 — soft is silent. Mailgun retries and most deliver; alarming a
    // tenant about a full mailbox manufactures a crisis out of a hiccup.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'severity' => 'temporary',
    ])))->assertOk();

    expect($message->case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('does NOT stop the case on a delivery', function () {
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'event' => 'delivered', 'severity' => null,
    ])))->assertOk();

    expect($message->case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
});

it('leaves a case that has ALREADY stopped exactly as it was', function (CaseStatus $from) {
    // D17.8 — a bounce stops a case that is still running; it does not
    // reach back into one that has already stopped. The event is still
    // recorded, because per D17.1 the record extends.
    $message = outboundMessage($from);

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message)))
        ->assertOk();

    expect($message->case->fresh()->status)->toBe($from)
        ->and(CaseEvent::where('event_type', 'delivery_failed')->count())->toBe(1);
})->with([
    'resolved' => [CaseStatus::Resolved],
    'abandoned' => [CaseStatus::Abandoned],
    'escalation_exhausted' => [CaseStatus::EscalationExhausted],
]);

it('notifies the tenant, mail-only, when it stops a case', function () {
    Mail::fake();
    $this->seed(LetterTemplateSeeder::class);
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message)))
        ->assertOk();

    Mail::assertQueued(AutoEscalationTenantNotice::class);
});

it('sends a DIFFERENT notice for a complaint than for a bounce', function () {
    // A bounce says the address is wrong and can be corrected. A complaint
    // says the letter arrived and was rejected — nothing to correct, and
    // per D17.5 no fork. Two templates, not one.
    Mail::fake();
    $this->seed(LetterTemplateSeeder::class);

    $bounced = outboundMessage();
    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($bounced)))->assertOk();

    $complained = outboundMessage();
    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($complained, [
        'id' => 'complaint-x', 'event' => 'complained', 'severity' => null,
    ])))->assertOk();

    $subjects = [];
    Mail::assertQueued(AutoEscalationTenantNotice::class, function ($mail) use (&$subjects) {
        $subjects[] = $mail->renderedSubject;

        return true;
    });

    expect($subjects)->toHaveCount(2)
        ->and($subjects[0])->not->toBe($subjects[1]);
});

it('does not notify when a temporary failure is recorded', function () {
    Mail::fake();
    $this->seed(LetterTemplateSeeder::class);
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'severity' => 'temporary',
    ])))->assertOk();

    Mail::assertNothingQueued();
});

it('still stops the case when no active template exists to notify with', function () {
    // Active-row idiom. Deactivating the template is a content decision,
    // not a licence to leave a case escalating against an address proven
    // dead. (Pest.php seeds letter_templates for every Feature test, so
    // the row has to be deactivated rather than merely absent.)
    Mail::fake();
    LetterTemplate::where('code', 'contact_failed_bounce')->update(['active' => false]);
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message)))
        ->assertOk();

    expect($message->case->fresh()->status)->toBe(CaseStatus::ContactFailed);
    Mail::assertNothingQueued();
});

it('writes NO case_messages row — the escalation counter cannot be perturbed', function () {
    // The counter is derived from outbound system rows with a non-null
    // stage_at_send. case_events is a different table precisely so that
    // nothing recorded here can reach that predicate, and the tenant
    // notification is mail-only for the same reason.
    Mail::fake();
    $this->seed(LetterTemplateSeeder::class);
    $message = outboundMessage();
    $before = CaseMessage::count();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message)))
        ->assertOk();

    expect(CaseMessage::count())->toBe($before);
});

it('never mutates the frozen letter it is reporting on', function () {
    $message = outboundMessage();
    $frozen = $message->only(['to_address_raw', 'subject', 'body_raw', 'stage_at_send']);

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message)))
        ->assertOk();

    expect($message->fresh()->only(['to_address_raw', 'subject', 'body_raw', 'stage_at_send']))
        ->toBe($frozen);
});

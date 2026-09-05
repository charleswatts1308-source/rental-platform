<?php

use App\Enums\CaseStatus;
use App\Models\CaseEvent;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

/**
 * #25 step 6 — the receiver writes the event into the case record.
 *
 * RECORD ONLY at this step. Reacting to a failure — the transition to
 * contact_failed, the tenant notification, the D17.3 copy — is step 7,
 * and the tests here pin that seam: a bounce arriving today must leave
 * the case's status exactly where it was.
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
| The seam: step 6 RECORDS, step 7 REACTS
|--------------------------------------------------------------------------
*/

it('does not change the case status — reacting is step 7', function (string $event) {
    // D17.5 makes a complaint terminal wherever it occurs, and D17.2 stops
    // the case on a permanent failure. NEITHER happens yet: step 6 records,
    // step 7 reacts. This is the seam.
    $message = outboundMessage();

    $this->postJson('/webhooks/mailgun/events', signedEvent(failedEvent($message, [
        'event' => $event,
    ])))->assertOk();

    expect($message->case->fresh()->status)->toBe(CaseStatus::AwaitingLandlord);
})->with(['failed', 'complained']);

it('writes NO case_messages row — the escalation counter cannot be perturbed', function () {
    // The counter is derived from outbound system rows with a non-null
    // stage_at_send. case_events is a different table precisely so that
    // nothing recorded here can reach that predicate.
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

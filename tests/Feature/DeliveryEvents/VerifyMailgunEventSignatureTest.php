<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

/**
 * #25 step 5 — the nested-signature verifier.
 *
 * The shapes asserted here are not invented. They come from three real
 * production sends captured on 23 Aug 2026 and written up in
 * docs/mailgun-delivery-event-payloads.md. The D0 inferred the nested
 * envelope; the capture run proved it.
 *
 * The pair of cross-rejection tests at the foot are the ones that matter
 * most: they pin WHY a second middleware exists at all. If someone later
 * "simplifies" the two into one that accepts either shape, those fail.
 */
beforeEach(function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key-shhh');

    Route::post('/test/delivery-event', fn () => response('ok', 200))
        ->middleware('verify.mailgun.event.signature');

    Route::post('/test/inbound-shape', fn () => response('ok', 200))
        ->middleware('verify.mailgun.signature');
});

/**
 * The real envelope shape: timestamp / token / signature NESTED under a
 * `signature` object, alongside `event-data`, delivered as JSON.
 */
function eventPayload(array $signatureOverrides = [], array $eventData = []): array
{
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $token = 'e6bfdcd34b66c293545a1969591ce2432c1b3a5df9c8592d5d';

    return [
        'signature' => array_merge([
            'token' => $token,
            'timestamp' => $timestamp,
            'signature' => hash_hmac('sha256', $timestamp.$token, $signingKey),
        ], $signatureOverrides),
        'event-data' => $eventData ?: [
            'event' => 'failed',
            'severity' => 'permanent',
            'reason' => 'generic',
            'recipient' => 'landlord@example.com',
            'user-variables' => ['case_message_id' => '19'],
        ],
    ];
}

it('accepts a correctly signed nested event payload', function () {
    $this->postJson('/test/delivery-event', eventPayload())
        ->assertOk()
        ->assertSee('ok');
});

it('refuses a payload with no signature envelope', function () {
    $this->postJson('/test/delivery-event', ['event-data' => ['event' => 'delivered']])
        ->assertStatus(406)
        ->assertSee('Missing signature envelope');
});

it('refuses each missing field inside the envelope', function (string $field) {
    $this->postJson('/test/delivery-event', eventPayload([$field => '']))
        ->assertStatus(406)
        ->assertSee('Missing signature fields');
})->with(['timestamp', 'token', 'signature']);

it('refuses a stale timestamp outside the replay window', function () {
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) (time() - 901);
    $token = 'stale-token';

    $this->postJson('/test/delivery-event', eventPayload([
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $timestamp.$token, $signingKey),
    ]))
        ->assertStatus(406)
        ->assertSee('Stale timestamp');
});

it('accepts a timestamp just inside the replay window', function () {
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) (time() - 890);
    $token = 'fresh-enough';

    $this->postJson('/test/delivery-event', eventPayload([
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $timestamp.$token, $signingKey),
    ]))->assertOk();
});

it('refuses a signature computed with the wrong key', function () {
    $timestamp = (string) time();
    $token = 'some-token';

    $this->postJson('/test/delivery-event', eventPayload([
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $timestamp.$token, 'not-the-signing-key'),
    ]))
        ->assertStatus(406)
        ->assertSee('Invalid signature');
});

it('refuses everything when no signing key is configured', function () {
    Config::set('services.mailgun.webhook_signing_key', '');

    $this->postJson('/test/delivery-event', eventPayload())
        ->assertStatus(406)
        ->assertSee('not configured');
});

it('signs over timestamp then token, in that order and with no separator', function () {
    // Guards the one detail that would silently break every verification
    // while looking plausible in a diff.
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $token = 'order-matters';

    $this->postJson('/test/delivery-event', eventPayload([
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $token.$timestamp, $signingKey),
    ]))->assertStatus(406);

    $this->postJson('/test/delivery-event', eventPayload([
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $timestamp.':'.$token, $signingKey),
    ]))->assertStatus(406);
});

/*
|--------------------------------------------------------------------------
| Why there are two middlewares (D0.2, proven 23 Aug)
|--------------------------------------------------------------------------
*/

it('refuses the FLAT inbound shape — that is the whole reason it exists', function () {
    // An inbound-routing payload posted at the event verifier. Correctly
    // signed by inbound's rules, and still refused: the nested envelope is
    // the contract here.
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $token = str_repeat('a', 50);

    $this->postJson('/test/delivery-event', [
        'timestamp' => $timestamp,
        'token' => $token,
        'signature' => hash_hmac('sha256', $timestamp.$token, $signingKey),
        'recipient' => 'abc123@mg.renters.rent',
    ])
        ->assertStatus(406)
        ->assertSee('Missing signature envelope');
});

it('confirms the OLD middleware cannot read an event payload', function () {
    // The failure #25 exists to prevent, pinned so a future "simplification"
    // that merges the two middlewares fails here.
    //
    // NOTE — this does NOT behave the way the D0 and
    // mailgun-delivery-event-payloads.md describe. Both say the inbound
    // verifier returns 406 on an event payload. It does not: line 39 casts
    // the nested `signature` ARRAY to string, Laravel promotes the warning
    // to an ErrorException, and the request 500s. Found 4 Sep 2026 writing
    // this test; the doc has been corrected.
    //
    // It matters because the argument for a second middleware was built on
    // "406, and Mailgun never retries a 406". A 500 is retried for about 8
    // hours. The conclusion is unchanged — a second middleware is still
    // required — but the reasoning was wrong, and a 500 is the LOUDER
    // failure of the two.
    //
    // Asserted as "not accepted" rather than as a specific status, so that
    // hardening the inbound middleware to refuse cleanly does not fail this.
    $response = $this->postJson('/test/inbound-shape', eventPayload());

    expect($response->status())->not->toBe(200);
});

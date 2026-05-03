<?php

use Illuminate\Support\Facades\Config;

beforeEach(function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key-shhh');
});

function signedPayload(array $extras = [], ?int $timestamp = null, ?string $signingKey = null): array
{
    $signingKey = $signingKey ?? config('services.mailgun.webhook_signing_key');
    $timestamp = $timestamp ?? time();
    $token = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    $signature = hash_hmac('sha256', $timestamp.$token, $signingKey);

    return array_merge([
        'timestamp' => (string) $timestamp,
        'token' => $token,
        'signature' => $signature,
    ], $extras);
}

it('accepts a request with a valid Mailgun signature', function () {
    $response = $this->post('/webhooks/mailgun/inbound', signedPayload());

    $response->assertStatus(200);
});

it('rejects a request with an invalid signature with 406', function () {
    $payload = signedPayload();
    $payload['signature'] = str_repeat('0', 64);

    $response = $this->post('/webhooks/mailgun/inbound', $payload);

    $response->assertStatus(406);
});

it('rejects a request signed with a different signing key with 406', function () {
    $payload = signedPayload(signingKey: 'attacker-guessed-this');

    $response = $this->post('/webhooks/mailgun/inbound', $payload);

    $response->assertStatus(406);
});

it('rejects a request with a stale timestamp with 406', function () {
    $payload = signedPayload(timestamp: time() - 3600); // 1 hour old

    $response = $this->post('/webhooks/mailgun/inbound', $payload);

    $response->assertStatus(406);
});

it('rejects a request with missing timestamp with 406', function () {
    $payload = signedPayload();
    unset($payload['timestamp']);

    $response = $this->post('/webhooks/mailgun/inbound', $payload);

    $response->assertStatus(406);
});

it('rejects a request with missing token with 406', function () {
    $payload = signedPayload();
    unset($payload['token']);

    $response = $this->post('/webhooks/mailgun/inbound', $payload);

    $response->assertStatus(406);
});

it('rejects a request with missing signature with 406', function () {
    $payload = signedPayload();
    unset($payload['signature']);

    $response = $this->post('/webhooks/mailgun/inbound', $payload);

    $response->assertStatus(406);
});

it('rejects with 406 when no signing key is configured', function () {
    Config::set('services.mailgun.webhook_signing_key', null);

    $response = $this->post('/webhooks/mailgun/inbound', signedPayload());

    $response->assertStatus(406);
});

it('does not reject the webhook route with CSRF (419) for missing token', function () {
    // Submit a POST without any session/CSRF token. The route is exempt from
    // CSRF, so the response status should be the middleware-driven 406, not
    // Laravel's default 419 Page Expired for missing CSRF token.
    $response = $this->post('/webhooks/mailgun/inbound', []);

    $response->assertStatus(406);
    expect($response->status())->not->toBe(419);
});

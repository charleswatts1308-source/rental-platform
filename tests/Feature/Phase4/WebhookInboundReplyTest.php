<?php

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key-shhh');
    Mail::fake();
});

function inboundPayload(string $tokenValue, array $overrides = []): array
{
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $signatureToken = str_repeat('a', 50);

    $base = [
        'timestamp' => $timestamp,
        'token' => $signatureToken,
        'signature' => hash_hmac('sha256', $timestamp.$signatureToken, $signingKey),
        'recipient' => $tokenValue.'@'.config('services.mailgun.inbound_domain'),
        'sender' => 'landlord@example.com',
        'from' => 'Mr Landlord <landlord@example.com>',
        'subject' => 'Re: repair issue',
        'body-html' => '<p>I will look at it next week.</p>',
        'body-plain' => 'I will look at it next week.',
        'Message-Id' => '<20260503184500.abcdef@mg.example.com>',
        'X-Mailgun-Spf' => 'Pass',
        'X-Mailgun-Dkim-Check-Result' => 'Pass',
    ];

    return array_merge($base, $overrides);
}

function caseWithActiveTokenIn(CaseStatus $status, ?string $tokenValue = null, ?string $landlordEmail = null): array
{
    $case = RepairCase::factory()->withLandlord([
        'email' => $landlordEmail ?? 'landlord@example.com',
    ])->create([
        'status' => $status,
    ]);
    $token = ReplyToken::factory()->create([
        'case_id' => $case->id,
        'token' => $tokenValue ?? Str::random(20),
        'bound_email' => $case->landlordRecipient()->email,
        'superseded_at' => null,
    ]);

    return [$case->fresh(), $token];
}

it('routes a valid inbound reply to the correct case', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord);

    $response = $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $response->assertStatus(200);
    $message = $case->fresh()->messages()->where('direction', MessageDirection::Inbound->value)->sole();
    expect($message->sender_role)->toBe(SenderRole::Landlord);
    expect($message->from_address_raw)->toBe('Mr Landlord <landlord@example.com>');
    expect($message->to_address_raw)->toBe($token->token.'@'.config('services.mailgun.inbound_domain'));
    expect($message->mailgun_message_id)->toBe('20260503184500.abcdef@mg.example.com');
    expect($message->spf_pass)->toBeTrue();
    expect($message->dkim_pass)->toBeTrue();
    expect($message->received_at)->not->toBeNull();
});

it('returns 200 and stores no message for an unknown token', function () {
    $unknownToken = str_repeat('z', 20);

    $response = $this->post('/webhooks/mailgun/inbound', inboundPayload($unknownToken));

    $response->assertStatus(200);
    expect(CaseMessage::count())->toBe(0);
});

it('returns 200 and stores no message for an expired token', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord);
    $token->update([
        'superseded_at' => now()->subDays(120),
        'expires_at' => now()->subDays(30),
    ]);

    $response = $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $response->assertStatus(200);
    expect($case->fresh()->messages()->where('direction', MessageDirection::Inbound->value)->count())->toBe(0);
});

it('routes a superseded but still in-window token correctly', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord);
    $token->update([
        'superseded_at' => now()->subDays(10),
        'expires_at' => now()->addDays(80),
    ]);

    $response = $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $response->assertStatus(200);
    expect($case->fresh()->messages()->where('direction', MessageDirection::Inbound->value)->count())->toBe(1);
});

it('returns 200 for a malformed recipient and stores no message', function () {
    $payload = inboundPayload('whatever');
    $payload['recipient'] = 'not-a-token@'.config('services.mailgun.inbound_domain');

    $response = $this->post('/webhooks/mailgun/inbound', $payload);

    $response->assertStatus(200);
    expect(CaseMessage::count())->toBe(0);
});

it('flags a sender mismatch with quarantine_reason and writes inbound_quarantined', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord, landlordEmail: 'landlord@example.com');

    $payload = inboundPayload($token->token, [
        'from' => 'Random Person <imposter@example.com>',
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    $message = $case->fresh()->messages()->where('direction', MessageDirection::Inbound->value)->sole();
    expect($message->quarantine_reason)->toBe('unexpected_from_address');
    expect($case->fresh()->events()->where('event_type', 'inbound_quarantined')->count())->toBe(1);
    expect($case->fresh()->events()->where('event_type', 'inbound_received')->count())->toBe(0);
});

it('treats case-insensitive matches and +suffix variants as the same sender', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord, landlordEmail: 'landlord@example.com');

    $payload = inboundPayload($token->token, [
        'from' => 'LANDLORD+repairs@Example.COM',
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    $message = $case->fresh()->messages()->where('direction', MessageDirection::Inbound->value)->sole();
    expect($message->quarantine_reason)->toBeNull();
});

it('strips script tags from body_sanitised but preserves them in body_raw', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord);

    $maliciousHtml = '<p>Hello</p><script>alert("xss")</script><p>End</p>';
    $payload = inboundPayload($token->token, ['body-html' => $maliciousHtml]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    $message = $case->fresh()->messages()->where('direction', MessageDirection::Inbound->value)->sole();
    expect($message->body_raw)->toContain('<script>');
    expect($message->body_sanitised)->not->toContain('<script>');
    expect($message->body_sanitised)->not->toContain('alert');
    expect($message->body_sanitised)->toContain('Hello');
});

it('falls back to body_plain when no HTML body is provided', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord);

    $payload = inboundPayload($token->token, [
        'body-html' => '',
        'body-plain' => 'Plain text reply only.',
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    $message = $case->fresh()->messages()->where('direction', MessageDirection::Inbound->value)->sole();
    expect($message->body_raw)->toBe('Plain text reply only.');
    expect($message->body_sanitised)->toBeNull();
});

it('transitions a case from awaiting_landlord to awaiting_tenant_review', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord);

    $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
});

it('transitions a case from on_hold to awaiting_tenant_review (hold preserved)', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::OnHold);
    $holdUntil = now()->addDays(7);
    $case->update(['hold_until' => $holdUntil]);
    $case->refresh();

    $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $fresh = $case->fresh();
    expect($fresh->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($fresh->hold_until?->toIso8601String())->toBe($holdUntil->toIso8601String());
});

it('wakes a dormant case to awaiting_tenant_review', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::Dormant);

    $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    expect($case->fresh()->status)->toBe(CaseStatus::AwaitingTenantReview);
});

it('appends the message but does not transition when already in awaiting_tenant_review', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingTenantReview);

    $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $fresh = $case->fresh();
    expect($fresh->status)->toBe(CaseStatus::AwaitingTenantReview);
    expect($fresh->messages()->where('direction', MessageDirection::Inbound->value)->count())->toBe(1);
    expect($fresh->events()->where('event_type', 'inbound_received')->count())->toBe(1);
});

// Phase 3 — TAR demolished; this test's specific status target no
// longer exists. The remaining "no transition on inbound" coverage is
// the awaiting_tenant_review case (already covered by another test in
// this file) — landlord-direction inbound during AwaitingTenantReview
// doesn't transition because AwaitingTenantReview ∉ STATES_THAT_TRANSITION.

it('stores the message but does not transition for a resolved (terminal) case', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::Resolved);

    $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $fresh = $case->fresh();
    expect($fresh->status)->toBe(CaseStatus::Resolved);
    expect($fresh->messages()->where('direction', MessageDirection::Inbound->value)->count())->toBe(1);
    expect($fresh->events()->where('event_type', 'inbound_received')->count())->toBe(1);
});

it('stores the message but does not transition for an abandoned (terminal) case', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::Abandoned);

    $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $fresh = $case->fresh();
    expect($fresh->status)->toBe(CaseStatus::Abandoned);
    expect($fresh->messages()->where('direction', MessageDirection::Inbound->value)->count())->toBe(1);
});

it('increments use_count and sets last_used_at on the routing token', function () {
    [$case, $token] = caseWithActiveTokenIn(CaseStatus::AwaitingLandlord);

    expect($token->use_count)->toBe(0);
    expect($token->last_used_at)->toBeNull();

    $this->post('/webhooks/mailgun/inbound', inboundPayload($token->token));

    $fresh = $token->fresh();
    expect($fresh->use_count)->toBe(1);
    expect($fresh->last_used_at)->not->toBeNull();
});

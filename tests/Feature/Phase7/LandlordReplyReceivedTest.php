<?php

use App\Enums\CaseStatus;
use App\Mail\Notifications\LandlordReplyReceived;
use App\Models\LandlordContact;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key-shhh');
    Mail::fake();
});

function inboundReplyPayload(string $token, string $fromEmail = 'landlord@example.com'): array
{
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $signatureToken = str_repeat('a', 50);

    return [
        'timestamp' => $timestamp,
        'token' => $signatureToken,
        'signature' => hash_hmac('sha256', $timestamp.$signatureToken, $signingKey),
        'recipient' => $token.'@'.config('services.mailgun.inbound_domain'),
        'sender' => $fromEmail,
        'from' => "Landlord <{$fromEmail}>",
        'subject' => 'Re: repair issue',
        'body-html' => '<p>I will look at it next week.</p>',
        'body-plain' => 'I will look at it next week.',
        'X-Mailgun-Spf' => 'Pass',
        'X-Mailgun-Dkim-Check-Result' => 'Pass',
    ];
}

function caseAwaitingLandlordWithToken(): array
{
    $tenant = User::factory()->create();
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com']);
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'landlord_contact_id' => $contact->id,
        'status' => CaseStatus::AwaitingLandlord,
    ]);
    $token = ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $contact->email,
        'superseded_at' => null,
    ]);

    return [$tenant, $case, $token];
}

it('queues a LandlordReplyReceived notification on a successful inbound webhook', function () {
    [$tenant, $case, $token] = caseAwaitingLandlordWithToken();

    $this->post('/webhooks/mailgun/inbound', inboundReplyPayload($token->token));

    Mail::assertQueued(LandlordReplyReceived::class, function (LandlordReplyReceived $mail) use ($tenant, $case) {
        return $mail->hasTo($tenant->email) && $mail->case->is($case);
    });
});

it('renders the notification with a privacy-safe subject', function () {
    [, $case] = caseAwaitingLandlordWithToken();

    $mail = new LandlordReplyReceived($case);
    $envelope = $mail->envelope();

    expect($envelope->subject)->toBe('Your repair case has a new reply');
    expect($envelope->subject)->not->toContain('@');
    expect($envelope->subject)->not->toContain($case->landlordContact->email);
});

it('renders the notification body with a deep link and no message excerpt', function () {
    [, $case] = caseAwaitingLandlordWithToken();
    $case->messages()->create([
        'direction' => 'inbound',
        'sender_role' => 'landlord',
        'body_raw' => 'Sensitive landlord words.',
        'body_sanitised' => '<p>Sensitive landlord words.</p>',
        'received_at' => now(),
    ]);

    $rendered = (new LandlordReplyReceived($case))->render();

    expect($rendered)->toContain(route('cases.show', $case->url_slug));
    expect($rendered)->not->toContain('Sensitive landlord words.');
});

it('does not queue a notification when the inbound token is unknown', function () {
    [, , $token] = caseAwaitingLandlordWithToken();

    $this->post('/webhooks/mailgun/inbound', inboundReplyPayload('unknownTokenXxXxXxXxX'));

    Mail::assertNothingQueued();
});

it('still queues a notification for inbound that lands in a terminal state (audit only)', function () {
    $tenant = User::factory()->create();
    $contact = LandlordContact::factory()->create(['email' => 'landlord@example.com']);
    $case = RepairCase::factory()->create([
        'tenant_user_id' => $tenant->id,
        'landlord_contact_id' => $contact->id,
        'status' => CaseStatus::Resolved,
        'closed_at' => now(),
    ]);
    $token = ReplyToken::factory()->create([
        'case_id' => $case->id,
        'bound_email' => $contact->email,
        'superseded_at' => null,
    ]);

    $this->post('/webhooks/mailgun/inbound', inboundReplyPayload($token->token));

    Mail::assertQueued(LandlordReplyReceived::class);
});

it('queues a notification even when the inbound is quarantined as sender mismatch', function () {
    [$tenant, , $token] = caseAwaitingLandlordWithToken();

    $this->post('/webhooks/mailgun/inbound', inboundReplyPayload($token->token, 'imposter@elsewhere.com'));

    Mail::assertQueued(LandlordReplyReceived::class, fn ($mail) => $mail->hasTo($tenant->email));
});

<?php

use App\Mail\InboundEnquiryForward;
use App\Models\CaseMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.mailgun.webhook_signing_key', 'test-signing-key-shhh');
    Config::set('services.mailgun.inbound_domain', 'mg.renters.rent');
    Config::set('enquiries.forward_to', 'private@example.com');
    Config::set('enquiries.local_parts', 'landlord-enquiries,privacy,info');
    Mail::fake();
});

function enquiryPayload(string $recipient, array $overrides = []): array
{
    $signingKey = (string) config('services.mailgun.webhook_signing_key');
    $timestamp = (string) time();
    $signatureToken = str_repeat('a', 50);

    return array_merge([
        'timestamp' => $timestamp,
        'token' => $signatureToken,
        'signature' => hash_hmac('sha256', $timestamp.$signatureToken, $signingKey),
        'recipient' => $recipient,
        'sender' => 'curious@landlord.example',
        'from' => 'A Landlord <curious@landlord.example>',
        'subject' => 'Who are you and why am I getting this?',
        'body-html' => '<p>Please take me off your list.</p>',
        'body-plain' => 'Please take me off your list.',
        'X-Mailgun-Spf' => 'Pass',
        'X-Mailgun-Dkim-Check-Result' => 'Pass',
    ], $overrides);
}

it('forwards each configured enquiry address to the configured mailbox', function (string $localPart) {
    $this->post('/webhooks/mailgun/inbound', enquiryPayload($localPart.'@mg.renters.rent'))
        ->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) use ($localPart) {
        return $mail->hasTo('private@example.com')
            && $mail->enquiryAddress === $localPart.'@mg.renters.rent';
    });
})->with(['landlord-enquiries', 'privacy', 'info']);

it('sends from the enquiry address and replies to the original sender', function () {
    $this->post('/webhooks/mailgun/inbound', enquiryPayload('landlord-enquiries@mg.renters.rent'))
        ->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        $envelope = $mail->envelope();

        return $envelope->from->address === 'landlord-enquiries@mg.renters.rent'
            && $envelope->replyTo[0]->address === 'curious@landlord.example';
    });
});

it('preserves the subject and names the address it arrived at in the body', function () {
    $this->post('/webhooks/mailgun/inbound', enquiryPayload('privacy@mg.renters.rent'))
        ->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        return $mail->renderedSubject === 'Who are you and why am I getting this?'
            && str_contains($mail->renderedBody, 'privacy@mg.renters.rent')
            && str_contains($mail->renderedBody, 'Please take me off your list.');
    });
});

it('writes no case_messages row for an enquiry', function () {
    $this->post('/webhooks/mailgun/inbound', enquiryPayload('info@mg.renters.rent'))
        ->assertStatus(200);

    expect(CaseMessage::count())->toBe(0);
});

it('drops an enquiry and sends nothing when the forward address is unset', function () {
    Config::set('enquiries.forward_to', null);

    $this->post('/webhooks/mailgun/inbound', enquiryPayload('info@mg.renters.rent'))
        ->assertStatus(200);

    Mail::assertNothingSent();
});

it('refuses to forward to a destination on the inbound domain', function () {
    Config::set('enquiries.forward_to', 'anything@mg.renters.rent');

    $this->post('/webhooks/mailgun/inbound', enquiryPayload('info@mg.renters.rent'))
        ->assertStatus(200);

    Mail::assertNothingSent();
});

it('ignores an enquiry local part arriving on some other domain', function () {
    $this->post('/webhooks/mailgun/inbound', enquiryPayload('privacy@not-our-domain.example'))
        ->assertStatus(200);

    Mail::assertNothingSent();
    expect(CaseMessage::count())->toBe(0);
});

it('sends nothing for a recipient that is neither a token nor an enquiry', function () {
    $this->post('/webhooks/mailgun/inbound', enquiryPayload('random-person@mg.renters.rent'))
        ->assertStatus(200);

    Mail::assertNothingSent();
    expect(CaseMessage::count())->toBe(0);
});

it('still forwards when the body is plain text only', function () {
    $payload = enquiryPayload('info@mg.renters.rent', ['body-html' => '']);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        return str_contains($mail->renderedBody, 'Please take me off your list.');
    });
});

it('says so in the body when the original carried attachments', function () {
    $payload = enquiryPayload('landlord-enquiries@mg.renters.rent', ['attachment-count' => 2]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        return str_contains($mail->renderedBody, 'NOT forwarded');
    });
});

/*
 * #74 — the SPF/DKIM line read "? / ?" on every real forward, because the
 * verdicts are not top-level payload keys. These use Mailgun's actual shape:
 * message-headers, a JSON array of [name, value] pairs.
 */

it('reads SPF and DKIM verdicts out of message-headers', function () {
    $payload = enquiryPayload('landlord-enquiries@mg.renters.rent', [
        'X-Mailgun-Spf' => null,
        'X-Mailgun-Dkim-Check-Result' => null,
        'message-headers' => json_encode([
            ['Received-SPF', 'pass (google.com: domain of curious@landlord.example designates 1.2.3.4)'],
            ['Authentication-Results', 'mx.google.com; spf=pass; dkim=pass header.i=@landlord.example'],
        ]),
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        // INVERTED 19 Sep 2026 (was: asserts "Pass / Pass" is present). The
        // line is now exception-only — a clean pair says nothing a reader can
        // act on, so it is suppressed. This assertion is STRONGER than the one
        // it replaces: it pins both the verdicts being read correctly AND the
        // suppression, where the old one pinned only the reading.
        return ! str_contains($mail->renderedBody, 'Sender checks')
            && str_contains($mail->renderedBody, 'Enquiry to:');
    });
});

it('reports a DKIM signature it cannot verify rather than claiming a pass', function () {
    $payload = enquiryPayload('privacy@mg.renters.rent', [
        'X-Mailgun-Spf' => null,
        'X-Mailgun-Dkim-Check-Result' => null,
        'message-headers' => json_encode([
            ['DKIM-Signature', 'v=1; a=rsa-sha256; d=landlord.example; s=s1'],
        ]),
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        return str_contains($mail->renderedBody, 'Signed (unverified)');
    });
});

it('says "not reported" rather than "?" when the payload carries no verdicts', function () {
    $payload = enquiryPayload('info@mg.renters.rent', [
        'X-Mailgun-Spf' => null,
        'X-Mailgun-Dkim-Check-Result' => null,
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        return str_contains($mail->renderedBody, 'Sender checks did not fully pass')
            && str_contains($mail->renderedBody, 'SPF not reported / DKIM not reported')
            && ! str_contains($mail->renderedBody, '? / ?');
    });
});

it('survives a malformed message-headers value', function () {
    $payload = enquiryPayload('info@mg.renters.rent', [
        'X-Mailgun-Spf' => null,
        'X-Mailgun-Dkim-Check-Result' => null,
        'message-headers' => 'not json at all',
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class);
});

/*
 * Exception-only display, ruled 19 Sep 2026: a clean pair is suppressed, and
 * anything short of a clean pair is shown as a warning. A pass never changes
 * what the reader does; a failure is the only verdict that should.
 */

it('warns prominently when SPF fails, even though DKIM passed', function () {
    $payload = enquiryPayload('landlord-enquiries@mg.renters.rent', [
        'X-Mailgun-Spf' => null,
        'X-Mailgun-Dkim-Check-Result' => null,
        'message-headers' => json_encode([
            ['Received-SPF', 'fail (google.com: domain does not designate 9.9.9.9 as permitted sender)'],
            ['Authentication-Results', 'mx.google.com; spf=fail; dkim=pass header.i=@landlord.example'],
        ]),
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        return str_contains($mail->renderedBody, 'Sender checks did not fully pass')
            && str_contains($mail->renderedBody, 'SPF Fail / DKIM Pass')
            && str_contains($mail->renderedBody, 'may not be from the domain it claims');
    });
});

it('treats a signed-but-unverified DKIM as worth warning about', function () {
    $payload = enquiryPayload('privacy@mg.renters.rent', [
        'X-Mailgun-Spf' => null,
        'X-Mailgun-Dkim-Check-Result' => null,
        'message-headers' => json_encode([
            ['Received-SPF', 'pass (designated sender)'],
            ['DKIM-Signature', 'v=1; a=rsa-sha256; d=landlord.example; s=s1'],
        ]),
    ]);

    $this->post('/webhooks/mailgun/inbound', $payload)->assertStatus(200);

    Mail::assertSent(InboundEnquiryForward::class, function (InboundEnquiryForward $mail) {
        return str_contains($mail->renderedBody, 'Sender checks did not fully pass');
    });
});

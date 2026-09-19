<?php

namespace App\Actions;

use App\Mail\InboundEnquiryForward;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;

/**
 * Stage 1 of the inbound-enquiry channel (docs/cc-brief-inbound-enquiries.md).
 *
 * A message arrived on the inbound domain at one of the configured enquiry
 * addresses — landlord-enquiries@, privacy@, info@ — rather than at a case
 * reply token. It is re-sent to the private mailbox in enquiries.forward_to.
 *
 * Stage 1 keeps NO RECORD: the enquiry exists only in that mailbox. That is
 * the accepted cost of giving #62's letter-1 sentence a working destination
 * without a schema change, and the whole reason stage 2 exists.
 *
 * Every path returns rather than throws. The caller answers Mailgun 200
 * regardless, so a throw here would buy nothing but a retry storm.
 */
class ForwardInboundEnquiry
{
    public function execute(array $payload, ?CarbonInterface $now = null): bool
    {
        $now ??= CarbonImmutable::now();

        $recipient = (string) ($payload['recipient'] ?? '');
        $forwardTo = trim((string) config('enquiries.forward_to', ''));

        if ($forwardTo === '') {
            // Deliberate: log and drop. A webhook that throws is worse than one
            // that records why it did nothing — but note the consequence, that
            // a missing key looks exactly like the silence this work removed.
            Log::warning('[LLCS] Inbound enquiry dropped: MAIL_ENQUIRY_FORWARD_TO is not set.', [
                'recipient' => $recipient,
                'environment' => app()->environment(),
            ]);

            return false;
        }

        if ($this->wouldLoop($forwardTo)) {
            Log::error('[LLCS] Inbound enquiry dropped: forward destination is on the inbound domain.', [
                'recipient' => $recipient,
                'forward_to' => $forwardTo,
            ]);

            return false;
        }

        $replyTo = $this->senderAddress($payload);

        Mail::to($forwardTo)->send(new InboundEnquiryForward(
            enquiryAddress: Str::lower(trim($recipient)),
            replyToAddress: $replyTo,
            renderedSubject: $this->subject($payload),
            renderedBody: $this->body($payload, $now),
        ));

        Log::info('[LLCS] Inbound enquiry forwarded.', [
            'recipient' => $recipient,
            'has_reply_to' => $replyTo !== null,
        ]);

        return true;
    }

    /**
     * A destination on the inbound domain would post straight back into this
     * webhook and run until Mailgun's limits stopped it.
     */
    private function wouldLoop(string $forwardTo): bool
    {
        $inboundDomain = Str::lower((string) config('services.mailgun.inbound_domain'));

        if ($inboundDomain === '' || ! Str::contains($forwardTo, '@')) {
            return false;
        }

        return Str::lower(Str::after($forwardTo, '@')) === $inboundDomain;
    }

    private function subject(array $payload): string
    {
        $subject = trim((string) ($payload['subject'] ?? ''));

        return $subject === '' ? '(no subject)' : $subject;
    }

    /**
     * Mailgun gives the bare address in `sender`; `from` carries the display
     * form. Prefer the bare one, fall back to extracting from the display.
     */
    private function senderAddress(array $payload): ?string
    {
        $sender = trim((string) ($payload['sender'] ?? ''));

        if ($sender !== '' && filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            return $sender;
        }

        $from = trim((string) ($payload['from'] ?? ''));
        $candidate = preg_match('/<([^>]+)>/', $from, $matches) === 1 ? trim($matches[1]) : $from;

        return filter_var($candidate, FILTER_VALIDATE_EMAIL) ? $candidate : null;
    }

    /**
     * The forwarded body: a short block naming which address it arrived at,
     * then the original message.
     *
     * The block is not decoration. Three addresses forward into one mailbox
     * and the To: line is the only thing that distinguishes them, so it is
     * stated in the body where it survives forwarding, quoting and printing.
     */
    private function body(array $payload, CarbonInterface $now): string
    {
        $bodyHtml = (string) ($payload['body-html'] ?? '');
        $bodyPlain = (string) ($payload['body-plain'] ?? '');

        $original = $bodyHtml !== ''
            ? Purifier::clean($bodyHtml)
            : nl2br(e($bodyPlain));

        if (trim(strip_tags($original)) === '') {
            $original = '<p><em>(The message had no readable body.)</em></p>';
        }

        $attachments = (int) ($payload['attachment-count'] ?? 0);
        $attachmentLine = $attachments > 0
            ? '<div><strong>Attachments:</strong> '.$attachments.' — NOT forwarded (stage 1). '
                .'Ask the sender to resend if you need them.</div>'
            : '';

        $spamFlag = trim((string) ($payload['X-Mailgun-Sflag'] ?? ''));
        $spamLine = Str::lower($spamFlag) === 'yes'
            ? '<div><strong>Mailgun flagged this as spam.</strong></div>'
            : '';

        $header = '<div style="background: #f4f4f4; color: #222; border-left: 4px solid #888; '
            .'padding: 12px 16px; margin-bottom: 20px; font-size: 13px;">'
            .'<div><strong>Enquiry to:</strong> '.e(Str::lower(trim((string) ($payload['recipient'] ?? '')))).'</div>'
            .'<div><strong>From:</strong> '.e((string) ($payload['from'] ?? 'unknown')).'</div>'
            .'<div><strong>Received:</strong> '.e($now->format('d M Y H:i')).'</div>'
            .$this->authLine($payload)
            .$attachmentLine
            .$spamLine
            .'<div style="margin-top: 8px; color: #666;">Not a case reply. Nothing was written to any '
            .'case record. Replying answers the sender directly.</div>'
            .'</div>';

        return $header.$original;
    }

    /**
     * The sender-authentication line — shown ONLY when something is wrong.
     *
     * Ruled 19 Sep 2026 after the live walk. The line used to print on every
     * forward, and read "Pass / Pass" almost every time, which trains a reader
     * to ignore the block it sits in. A pass never changes what you do; only a
     * failure does. So: silent when clean, loud when not.
     *
     * What a pass actually means, since it is easy to over-read: the message
     * genuinely came from the domain it claims and was not altered in transit.
     * It says NOTHING about who the human is — anyone can open a free webmail
     * account — so on gmail.com or outlook.com a pass is uninformative. It
     * earns its place when the sender's DOMAIN means something: a letting
     * agency asking to change an address or close a case is exactly where a
     * failure matters.
     */
    private function authLine(array $payload): string
    {
        $spf = $this->authResult($payload, 'spf');
        $dkim = $this->authResult($payload, 'dkim');

        if (Str::lower($spf) === 'pass' && Str::lower($dkim) === 'pass') {
            return '';
        }

        return '<div style="margin-top: 6px; padding: 6px 8px; background: #fff3cd; '
            .'border: 1px solid #ffc107; color: #533f03;">'
            .'<strong>Sender checks did not fully pass — SPF '.e($spf).' / DKIM '.e($dkim).'.</strong> '
            .'This may not be from the domain it claims. Treat any instruction in it with care.'
            .'</div>';
    }

    /**
     * The SPF or DKIM verdict for a forwarded enquiry.
     *
     * #74 — this used to read two TOP-LEVEL payload keys (`X-Mailgun-Spf`,
     * `X-Mailgun-Dkim-Check-Result`). Mailgun does not send them there, so the
     * line read "? / ?" on every real forward. The suite agreed with the bug
     * because the fixture invented the same two keys: a payload shape written
     * from assumption rather than from observed bytes, which is exactly what
     * docs/mailgun-delivery-event-payloads.md exists to stop.
     *
     * So this looks in three places, cheapest first, and never claims a
     * verdict it did not find:
     *   1. the top-level keys, in case a future payload does carry them;
     *   2. `message-headers`, Mailgun's JSON array of [name, value] pairs —
     *      Received-SPF, Authentication-Results, DKIM-Signature;
     *   3. nothing → "not reported", which is honest, unlike "?".
     */
    private function authResult(array $payload, string $kind): string
    {
        $topLevel = $kind === 'spf'
            ? trim((string) ($payload['X-Mailgun-Spf'] ?? ''))
            : trim((string) ($payload['X-Mailgun-Dkim-Check-Result'] ?? ''));

        if ($topLevel !== '') {
            return $topLevel;
        }

        $headers = $this->messageHeaders($payload);

        if ($kind === 'spf') {
            $received = $headers['received-spf'] ?? '';

            if ($received !== '') {
                return Str::title(Str::before(trim($received), ' '));
            }
        }

        $auth = $headers['authentication-results'] ?? '';

        if ($auth !== '' && preg_match('/\b'.$kind.'=([a-z]+)/i', $auth, $matches) === 1) {
            return Str::title($matches[1]);
        }

        if ($kind === 'dkim' && ($headers['dkim-signature'] ?? '') !== '') {
            return 'Signed (unverified)';
        }

        return 'not reported';
    }

    /**
     * Mailgun's `message-headers` is a JSON array of [name, value] pairs.
     * Returned lower-cased by name; a later duplicate does not overwrite an
     * earlier one, matching how a reader sees the topmost header.
     *
     * @return array<string, string>
     */
    private function messageHeaders(array $payload): array
    {
        $raw = (string) ($payload['message-headers'] ?? '');

        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return [];
        }

        $headers = [];

        foreach ($decoded as $pair) {
            if (! is_array($pair) || count($pair) < 2) {
                continue;
            }

            $name = Str::lower(trim((string) $pair[0]));

            if ($name !== '' && ! array_key_exists($name, $headers)) {
                $headers[$name] = (string) $pair[1];
            }
        }

        return $headers;
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Classifies the recipient of an inbound Mailgun payload.
 *
 * One Mailgun route catches everything on the inbound domain (the free
 * tier allows only one), so the application decides what each address
 * means. Three outcomes, and MailgunInboundController asks in this order:
 *
 *   1. a case reply   — 20 alphanumeric characters before the "@";
 *   2. an enquiry     — a configured local part on the inbound domain;
 *   3. anything else  — dropped, as it always has been.
 *
 * The token shape here is the DISPATCHER'S copy. The canonical extraction
 * still lives in HandleInboundReply::extractToken, which this work does
 * not touch. The two must agree: if one changes, change both. The
 * regression test that a real reply still binds to its case is what
 * catches a drift between them.
 */
class InboundRecipient
{
    /** A reply token is exactly 20 alphanumeric characters. */
    public static function isCaseToken(string $recipient): bool
    {
        return preg_match('/^[A-Za-z0-9]{20}@/', $recipient) === 1;
    }

    /**
     * True when the recipient is one of the configured enquiry addresses
     * ON THE INBOUND DOMAIN. The domain check matters: it is what stops a
     * spoofed "privacy@somewhere-else" from being treated as ours.
     */
    public static function isEnquiry(string $recipient): bool
    {
        $domain = (string) config('services.mailgun.inbound_domain');

        if ($domain === '' || ! Str::contains($recipient, '@')) {
            return false;
        }

        [$localPart, $recipientDomain] = explode('@', Str::lower(trim($recipient)), 2);

        if ($recipientDomain !== Str::lower($domain)) {
            return false;
        }

        return in_array($localPart, self::enquiryLocalParts(), true);
    }

    /** @return array<int, string> */
    public static function enquiryLocalParts(): array
    {
        $configured = (string) config('enquiries.local_parts', '');

        return collect(explode(',', $configured))
            ->map(fn (string $part) => Str::lower(trim($part)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}

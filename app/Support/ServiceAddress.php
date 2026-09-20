<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The service's own public address, for mail renters.rent sends AS ITSELF —
 * as distinct from a letter sent on a tenant's behalf (which uses
 * MAILGUN_CASES_FROM_ADDRESS) or a case reply token.
 *
 * Derived from the inbound domain rather than configured separately, so it
 * is an address the enquiry forwarder already recognises: a user who hits
 * Reply on anything sent from here reaches info@, which the webhook
 * forwards to the private mailbox. That is what makes Contact Us two-way
 * without building threading (#30).
 *
 * Falls back to the framework's own From address if the inbound domain is
 * not configured — a box that cannot receive should still be able to send.
 */
class ServiceAddress
{
    public const LOCAL_PART = 'info';

    public static function contact(): string
    {
        $domain = trim((string) config('services.mailgun.inbound_domain'));

        if ($domain === '') {
            return (string) config('mail.from.address');
        }

        return self::LOCAL_PART.'@'.Str::lower($domain);
    }
}

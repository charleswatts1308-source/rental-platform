<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the HMAC signature on Mailgun DELIVERY EVENT webhooks (#25).
 *
 * WHY THIS IS A SECOND MIDDLEWARE AND NOT A WIDENING OF THE FIRST
 * Confirmed from real captured payloads on 23 Aug 2026, not inferred —
 * see docs/mailgun-delivery-event-payloads.md §1.
 *
 * Inbound ROUTING carries timestamp / token / signature FLAT at the top
 * level and arrives form-encoded. Delivery EVENTS nest the same three
 * fields under a `signature` object and arrive as JSON:
 *
 *   { "signature": { "token": ..., "timestamp": ..., "signature": ... },
 *     "event-data": { ... } }
 *
 * VerifyMailgunSignature reads the flat fields, so an event takes its
 * "Missing signature fields" branch and returns 406. Mailgun treats 406
 * as a DELIBERATE REFUSAL and never retries — events would be discarded
 * permanently and silently while the dashboard showed a healthy webhook.
 * Widening the existing middleware to accept either shape would also
 * weaken the inbound route, which has no business accepting a nested
 * envelope.
 *
 * The verification itself is identical: HMAC-SHA256 over timestamp
 * concatenated with token, in that order, no separator, hex-encoded,
 * using the same webhook signing key.
 */
class VerifyMailgunEventSignature
{
    /**
     * Matches VerifyMailgunSignature. Mailgun delivers within seconds in
     * normal operation, so the window is forgiving but bounded.
     */
    private const REPLAY_WINDOW_SECONDS = 900;

    public function handle(Request $request, Closure $next): Response
    {
        $signingKey = (string) config('services.mailgun.webhook_signing_key');

        if ($signingKey === '') {
            return response('Mailgun webhook signing key not configured', 406);
        }

        $envelope = $request->input('signature');

        // The nested shape is the whole point of this middleware. A flat
        // payload here is an inbound-routing request on the wrong route,
        // not an event we should try to verify.
        if (! is_array($envelope)) {
            return response('Missing signature envelope', 406);
        }

        $timestamp = (string) ($envelope['timestamp'] ?? '');
        $token = (string) ($envelope['token'] ?? '');
        $signature = (string) ($envelope['signature'] ?? '');

        if ($timestamp === '' || $token === '' || $signature === '') {
            return response('Missing signature fields', 406);
        }

        if (abs(time() - (int) $timestamp) > self::REPLAY_WINDOW_SECONDS) {
            return response('Stale timestamp', 406);
        }

        $expected = hash_hmac('sha256', $timestamp.$token, $signingKey);

        if (! hash_equals($expected, $signature)) {
            return response('Invalid signature', 406);
        }

        return $next($request);
    }
}

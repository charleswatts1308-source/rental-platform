<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the HMAC signature Mailgun attaches to inbound webhook requests.
 *
 * Mailgun's signature is HMAC-SHA256 of (timestamp . token), hex-encoded,
 * using the configured webhook signing key. Form fields:
 *   - timestamp  unix epoch seconds (string)
 *   - token      ~50-char random nonce per delivery
 *   - signature  hex digest to compare against
 *
 * Rejects with 406 (Not Acceptable) on any failure. Mailgun stops retrying
 * 406 responses, which is what we want for permanently-bad signatures.
 *
 * A 15-minute replay window guards against captured-and-replayed payloads.
 * Mailgun delivers within seconds in normal operation, so the window is
 * forgiving but bounded.
 */
class VerifyMailgunSignature
{
    private const REPLAY_WINDOW_SECONDS = 900;

    public function handle(Request $request, Closure $next): Response
    {
        $signingKey = (string) config('services.mailgun.webhook_signing_key');

        if ($signingKey === '') {
            return response('Mailgun webhook signing key not configured', 406);
        }

        $timestamp = (string) $request->input('timestamp', '');
        $token = (string) $request->input('token', '');
        $signature = (string) $request->input('signature', '');

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

<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\ForwardInboundEnquiry;
use App\Actions\HandleInboundReply;
use App\Http\Controllers\Controller;
use App\Support\InboundRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles forwarded inbound emails from Mailgun's inbox route.
 *
 * The route is wrapped in VerifyMailgunSignature middleware, so by the
 * time the request reaches __invoke() the signature has been validated.
 *
 * ONE Mailgun route catches everything on the inbound domain — the free
 * tier allows no more — so this controller decides what each address
 * means (docs/cc-brief-inbound-enquiries.md):
 *
 *   1. a 20-character reply token  → HandleInboundReply, unchanged;
 *   2. a configured enquiry address → ForwardInboundEnquiry;
 *   3. anything else                → logged and dropped, as before.
 *
 * The token test stays FIRST and stays untouched: case replies are the
 * evidential path and this dispatch must be invisible to them.
 *
 * Always 200 — even on unknown/expired tokens, unrecognised addresses and
 * a missing forward config — so we don't leak token validity to attackers
 * via timing or bounce, per the design.
 */
class MailgunInboundController extends Controller
{
    public function __invoke(
        Request $request,
        HandleInboundReply $handleReply,
        ForwardInboundEnquiry $forwardEnquiry,
    ): Response {
        $payload = $request->all();
        $recipient = (string) ($payload['recipient'] ?? '');

        if (InboundRecipient::isCaseToken($recipient)) {
            $handleReply->execute($payload);

            return response('', 200);
        }

        if (InboundRecipient::isEnquiry($recipient)) {
            $forwardEnquiry->execute($payload);

            return response('', 200);
        }

        Log::info('[LLCS] Inbound webhook: recipient is neither a case token nor an enquiry address.', [
            'recipient' => $recipient,
        ]);

        return response('', 200);
    }
}

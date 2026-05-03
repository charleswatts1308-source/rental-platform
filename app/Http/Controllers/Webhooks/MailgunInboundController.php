<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles forwarded inbound emails from Mailgun's inbox route.
 *
 * The route is wrapped in VerifyMailgunSignature middleware, so by the time
 * the request reaches __invoke() the signature has been validated. This
 * controller is intentionally thin: it delegates the parsing and side-effect
 * logic to the HandleInboundReply action.
 *
 * The controller always returns 200 OK once past signature verification —
 * even on unknown/expired tokens — so we don't leak token validity to
 * attackers via timing or bounce, per the design's security model.
 */
class MailgunInboundController extends Controller
{
    public function __invoke(Request $request): Response
    {
        // Phase 4 next commit wires HandleInboundReply here.
        return response('', 200);
    }
}

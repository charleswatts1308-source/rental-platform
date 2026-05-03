<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\HandleInboundReply;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles forwarded inbound emails from Mailgun's inbox route.
 *
 * The route is wrapped in VerifyMailgunSignature middleware, so by the
 * time the request reaches __invoke() the signature has been validated.
 * This controller is intentionally thin: it delegates parsing and side
 * effects to the HandleInboundReply action, then always responds with
 * 200 OK — even on unknown/expired tokens — so we don't leak token
 * validity to attackers via timing or bounce per the design.
 */
class MailgunInboundController extends Controller
{
    public function __invoke(Request $request, HandleInboundReply $action): Response
    {
        $action->execute($request->all());

        return response('', 200);
    }
}

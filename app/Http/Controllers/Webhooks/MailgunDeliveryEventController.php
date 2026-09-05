<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\RecordDeliveryEvent;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * #25 — receives Mailgun delivery-event webhooks.
 *
 * Separate from MailgunInboundController because the two shapes differ in
 * both places that matter: events nest the signature fields under a
 * `signature` object and arrive as JSON, where inbound routing carries
 * them flat and form-encoded. The signature difference is handled by the
 * middleware (verify.mailgun.event.signature); the body difference is
 * handled here by reading `event-data`.
 *
 * ALWAYS 200 once the signature has verified. A non-2xx makes Mailgun
 * retry for hours, and every outcome this route can reach — recorded,
 * duplicate, unmatched, unhandled — is final: no retry would change it.
 * Verification failure is the middleware's business and answers 406
 * before the request arrives here.
 */
class MailgunDeliveryEventController extends Controller
{
    public function __invoke(Request $request, RecordDeliveryEvent $action): Response
    {
        $eventData = $request->input('event-data');

        if (! is_array($eventData)) {
            return response('', 200);
        }

        $action->execute($eventData);

        return response('', 200);
    }
}

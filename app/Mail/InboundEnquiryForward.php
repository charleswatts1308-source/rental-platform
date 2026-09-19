<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Relays a non-case enquiry to the private mailbox configured in
 * enquiries.forward_to.
 *
 * NOT evidential correspondence. It MUST NOT create a case_messages row —
 * the escalation counter derives from outbound system rows with a non-null
 * stage_at_send, so a stray row inflates the ladder. Same invariant
 * AutoEscalationTenantNotice respects.
 *
 * From is the enquiry address the mail arrived at, NOT the original
 * sender: the inbound domain is one Mailgun signs, so SPF and DKIM stay
 * aligned. A plain forward preserving the sender's From would break their
 * SPF and invite spam foldering — the failure mode a raw Mailgun forward
 * route would have carried.
 *
 * Reply-To is the original sender, so Reply in any mail client answers the
 * person who wrote in. Note what that does NOT solve: the reply leaves from
 * whatever address the reading mailbox sends as, which is why a send-as
 * identity is wanted before replies become routine.
 *
 * Body bytes are composed by ForwardInboundEnquiry; this mailable only
 * relays them.
 */
class InboundEnquiryForward extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $enquiryAddress,
        public ?string $replyToAddress,
        public string $renderedSubject,
        public string $renderedBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->enquiryAddress, 'renters.rent enquiries'),
            replyTo: $this->replyToAddress === null
                ? []
                : [new Address($this->replyToAddress)],
            subject: $this->renderedSubject,
            // Environment tag, as CaseNotice does, so a misrouted forward is
            // visible in Mailgun's per-domain log view.
            tags: [app()->environment()],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderedBody);
    }
}

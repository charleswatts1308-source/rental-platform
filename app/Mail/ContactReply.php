<?php

namespace App\Mail;

use App\Models\ContactMessage;
use App\Support\ServiceAddress;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactReply extends Mailable
{
    public function __construct(public ContactMessage $contactMessage)
    {
    }

    /**
     * #30 — this used to send from noreply@renters.rent, with no Reply-To.
     *
     * Two faults in one line. The apex domain has no mailbox, so a user who
     * read our answer and hit Reply was writing into nothing: their follow-up
     * vanished and neither side learned that it had. And "noreply" told them
     * not to bother trying, on a platform whose whole proposition is that
     * correspondence does not get lost.
     *
     * It now sends from the service address on the inbound domain, which the
     * enquiry forwarder recognises. A reply reaches the webhook, is forwarded
     * to the private mailbox, and the conversation continues — reusing the
     * mechanism built for landlord enquiries rather than inventing threading.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(ServiceAddress::contact(), 'renters.rent'),
            subject: 'Re: '.$this->contactMessage->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contact-reply',
        );
    }
}

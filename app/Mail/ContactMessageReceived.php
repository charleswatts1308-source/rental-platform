<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * #30 — tells Charlie that a Contact Us message arrived.
 *
 * Before this, store() wrote a row and did nothing else: the only way to
 * learn a message existed was to log into admin and look, while the user
 * had been told on screen "we will get back to you soon". That promise was
 * kept only by memory.
 *
 * REPLY-TO IS THE USER. That is the whole mechanism — answering from an
 * ordinary mail client reaches the person who wrote in, with no admin
 * screen and no threading to build. From is the service's own address, so
 * SPF and DKIM stay aligned to a domain Mailgun signs.
 *
 * Deliberately NOT the #30 rebuild. The August decision was a real
 * two-way threaded conversation; this is the cheap 90% while volume is
 * low, and it means the reply Charlie sends lives in his mailbox and NOT
 * in the platform. Same trade as the stage 1 enquiry forwarder, taken
 * knowingly. The token machinery from cases is what a real thread would
 * reuse if the volume ever justifies it.
 */
class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactMessage $contactMessage,
        public string $fromAddress,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, 'renters.rent contact form'),
            replyTo: [new Address(
                $this->contactMessage->user->email,
                $this->contactMessage->user->name ?? '',
            )],
            subject: 'Contact Us: '.$this->contactMessage->subject,
            tags: [app()->environment()],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message-received');
    }
}

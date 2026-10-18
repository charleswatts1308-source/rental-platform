<?php

namespace App\Mail\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * #81 — "Thanks for registering", sent once when a user verifies their
 * email address (SendWelcomeEmail, on the Verified event).
 *
 * Account mail, not case mail. It MUST NOT create a case_messages row —
 * there is no case, and the escalation counter predicate depends on
 * tenant mail staying out of that table.
 *
 * Body and subject are pre-rendered by the listener against the active
 * welcome_registered template row (active-row idiom: no active row → no
 * send). This mailable just relays the bytes.
 */
class WelcomeRegistered extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $renderedSubject,
        public string $renderedBody,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderedSubject);
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->renderedBody);
    }
}

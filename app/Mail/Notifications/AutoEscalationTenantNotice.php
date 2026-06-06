<?php

namespace App\Mail\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Private tenant-facing notification fired by silence:sweep when it
 * auto-escalates a landlord-side case: "we've sent notice N on your
 * behalf to your landlord."
 *
 * IMPORTANT — this is tenant app surface, NOT evidential
 * correspondence. It MUST NOT create a case_messages row. The counter
 * predicate (SilenceClock::escalationCounter) depends on outbound
 * system case_messages rows being escalation letters only; a tenant
 * notification persisted there would inflate the counter and break
 * shadow-correct sends. See SilenceClock docblock (ruling-d).
 *
 * Body and subject are pre-rendered by the sweep against an active
 * tenant_notification template row (active-row idiom: no active row
 * → no send, no mailable construction). This mailable just relays
 * the bytes the sweep already rendered.
 */
class AutoEscalationTenantNotice extends Mailable
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

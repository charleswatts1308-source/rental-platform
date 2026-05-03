<?php

namespace App\Mail\Notifications;

use App\Models\RepairCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the tenant that the landlord has replied to one of their
 * repair cases. Subject and body are deliberately neutral — no
 * landlord email, no excerpt of the reply — so the inbox preview pane
 * never leaks correspondence content. The body links to the in-app
 * detail page where body_sanitised is the only thing the tenant ever
 * sees of the reply.
 */
class LandlordReplyReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RepairCase $case) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your repair case has a new reply',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.landlord-reply-received',
            with: [
                'case' => $this->case,
                'caseUrl' => route('cases.show', $this->case->url_slug),
            ],
        );
    }
}

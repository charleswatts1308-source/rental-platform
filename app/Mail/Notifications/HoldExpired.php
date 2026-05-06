<?php

namespace App\Mail\Notifications;

use App\Models\RepairCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the tenant that the hold they previously placed on a case
 * has expired — the daily SweepHolds released it back to
 * tenant_action_required so they can pick up where they left off.
 */
class HoldExpired extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RepairCase $case) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'The hold on your repair case has ended',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.hold-expired',
            with: [
                'case' => $this->case,
                'caseUrl' => route('cases.show', $this->case->url_slug),
            ],
        );
    }
}

<?php

namespace App\Mail\Notifications;

use App\Models\RepairCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Nudges a tenant whose case has sat in tenant_action_required for the
 * configured day_offset (7 or 14) without activity. Sent by
 * SweepDormancy alongside the dormancy_reminder_sent case_event so
 * that re-running the sweep on the same day doesn't duplicate either
 * the event or the mail. dayOffset reaches the template so the body
 * can adjust phrasing ("a week", "two weeks") without exposing
 * landlord content.
 */
class DormancyReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RepairCase $case, public int $dayOffset) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A reminder about your repair case',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.dormancy-reminder',
            with: [
                'case' => $this->case,
                'caseUrl' => route('cases.show', $this->case->url_slug),
                'dayOffset' => $this->dayOffset,
            ],
        );
    }
}

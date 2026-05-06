<?php

namespace App\Mail\Notifications;

use App\Models\RepairCase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the tenant that their case has reached the next-escalation
 * window — the daily SweepEscalations transitioned awaiting_landlord
 * → tenant_action_required because next_stage_eligible_at lapsed
 * without a landlord reply. Subject is neutral and the body just
 * surfaces the deep link.
 */
class EscalationEligible extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public RepairCase $case) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your repair case is ready for the next step',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notifications.escalation-eligible',
            with: [
                'case' => $this->case,
                'caseUrl' => route('cases.show', $this->case->url_slug),
            ],
        );
    }
}

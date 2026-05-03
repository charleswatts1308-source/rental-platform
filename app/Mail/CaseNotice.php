<?php

namespace App\Mail;

use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CaseNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairCase $case,
        public CaseMessage $message,
        public ReplyToken $token,
    ) {}

    public function envelope(): Envelope
    {
        $tenantFirstName = $this->tenantFirstName();

        return new Envelope(
            from: new Address('cases@mg.renters.rent', "{$tenantFirstName} via renters.rent"),
            replyTo: [new Address("{$this->token->token}@inbox.renters.rent")],
            subject: $this->subjectForStage(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.case-notices.'.$this->message->template_key,
            with: [
                'case' => $this->case,
                'caseMessage' => $this->message,
                'category' => $this->case->category,
                'property' => $this->case->property,
                'tenantFirstName' => $this->tenantFirstName(),
                'propertyAddress' => $this->propertyAddress(),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }

    private function tenantFirstName(): string
    {
        return explode(' ', trim((string) $this->case->tenant->name))[0] ?: 'Tenant';
    }

    private function propertyAddress(): string
    {
        $p = $this->case->property;

        return implode(', ', array_filter([
            $p->address_line1,
            $p->address_line2,
            $p->city,
            $p->postcode,
        ]));
    }

    private function subjectForStage(): string
    {
        $shortAddress = ($this->case->property->address_line1).', '.($this->case->property->postcode);

        return match ($this->message->stage_at_send) {
            1 => 'Repair issue notification — '.$shortAddress,
            2 => 'Follow-up: repair issue — '.$shortAddress,
            3 => 'Formal warning: repair issue — '.$shortAddress,
            4 => 'Pre-action letter: repair issue — '.$shortAddress,
            default => 'Repair correspondence — '.$shortAddress,
        };
    }
}

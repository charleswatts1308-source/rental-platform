<?php

namespace App\Mail;

use App\Models\CaseMessage;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class CaseNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairCase $case,
        public CaseMessage $message,
        public ReplyToken $token,
    ) {
        if (blank(config('services.mailgun.cases_from_address'))) {
            throw new RuntimeException(
                'MAILGUN_CASES_FROM_ADDRESS is not configured. Refusing to send a case notice '
                .'without an explicit From address — set it in this environment\'s .env.'
            );
        }

        if (blank(config('services.mailgun.inbound_domain'))) {
            throw new RuntimeException(
                'MAILGUN_INBOUND_DOMAIN is not configured. Refusing to send a case notice '
                .'without an explicit inbound reply domain — set it in this environment\'s .env.'
            );
        }
    }

    public function envelope(): Envelope
    {
        $tenantFirstName = $this->tenantFirstName();

        return new Envelope(
            from: new Address((string) config('services.mailgun.cases_from_address'), "{$tenantFirstName} via renters.rent"),
            replyTo: [new Address("{$this->token->token}@".config('services.mailgun.inbound_domain'))],
            subject: $this->subjectForStage(),
            // Tags the message with the environment (production/staging/local) so a
            // misrouted send is instantly visible in Mailgun's per-domain log view.
            // Laravel maps Envelope tags to Mailgun's o:tag (via Symfony TagHeader).
            tags: [app()->environment()],
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
        return $this->message->attachments
            ->map(fn ($attachment) => Attachment::fromStorageDisk($attachment->disk, $attachment->path)
                ->as($attachment->original_filename ?? basename($attachment->path))
                ->withMime($attachment->mime_type))
            ->all();
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

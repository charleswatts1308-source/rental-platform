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
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends a pre-rendered case notice to the landlord.
 *
 * IMPORTANT — the body and subject are FROZEN on $message at
 * orchestration time (see SendCaseNotice). This mailable never
 * re-renders: envelope reads $message->subject; content reads
 * $message->body_raw. Even on QUEUE_CONNECTION other than sync, the
 * worker sends the exact bytes the orchestrator recorded as evidence.
 *
 * The mailable still owns envelope concerns (From, Reply-To, tagging)
 * and attachment dispatch — those are not part of the rendered body.
 */
class CaseNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairCase $case,
        public CaseMessage $message,
        public ReplyToken $token,
    ) {
        if (blank(config('services.mailgun.cases_from_address'))) {
            $reason = 'MAILGUN_CASES_FROM_ADDRESS is not configured. Refusing to send a case notice '
                .'without an explicit From address — set it in this environment\'s .env.';
            Log::error('[LLCS] CaseNotice aborted: '.$reason, [
                'case_id' => $this->case->id,
                'environment' => app()->environment(),
            ]);
            throw new RuntimeException($reason);
        }

        if (blank(config('services.mailgun.inbound_domain'))) {
            $reason = 'MAILGUN_INBOUND_DOMAIN is not configured. Refusing to send a case notice '
                .'without an explicit inbound reply domain — set it in this environment\'s .env.';
            Log::error('[LLCS] CaseNotice aborted: '.$reason, [
                'case_id' => $this->case->id,
                'environment' => app()->environment(),
            ]);
            throw new RuntimeException($reason);
        }

        if (blank($this->message->subject) || blank($this->message->body_raw)) {
            $reason = 'CaseNotice received a message row without a frozen subject or body. '
                .'SendCaseNotice must populate both before queueing.';
            Log::error('[LLCS] CaseNotice aborted: '.$reason, [
                'case_id' => $this->case->id,
                'message_id' => $this->message->id,
            ]);
            throw new RuntimeException($reason);
        }
    }

    public function envelope(): Envelope
    {
        $tenantFirstName = $this->tenantFirstName();

        return new Envelope(
            from: new Address((string) config('services.mailgun.cases_from_address'), "{$tenantFirstName} via renters.rent"),
            replyTo: [new Address("{$this->token->token}@".config('services.mailgun.inbound_domain'))],
            subject: $this->message->subject,
            // Tags the message with the environment (production/staging/local) so a
            // misrouted send is instantly visible in Mailgun's per-domain log view.
            // Laravel maps Envelope tags to Mailgun's o:tag (via Symfony TagHeader).
            tags: [app()->environment()],
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->message->body_raw);
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
}

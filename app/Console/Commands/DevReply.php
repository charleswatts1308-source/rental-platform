<?php

namespace App\Console\Commands;

use App\Enums\LandlordContactRole;
use App\Enums\MessageDirection;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Simulates an inbound landlord reply for local/staging dev by POSTing a
 * correctly-signed Mailgun inbound payload to the real webhook route
 * in-process.
 *
 * The request runs the full production path — VerifyMailgunSignature
 * middleware, MailgunInboundController, then the HandleInboundReply action
 * — rather than calling the action directly, so signature handling and
 * routing are exercised too.
 *
 * Because the command and the middleware share one process, when no
 * MAILGUN_WEBHOOK_SIGNING_KEY is configured we set a dev key at runtime
 * and sign with it, so the signature the middleware verifies is the one we
 * just produced.
 */
class DevReply extends Command
{
    private const DEV_SIGNING_KEY = 'dev-mailgun-webhook-signing-key';

    protected $signature = 'dev:reply
        {--case= : Case id or url_slug (defaults to the latest case with an active reply token)}
        {--from= : Sender address (defaults to the landlord email; mismatch triggers quarantine)}
        {--subject=Re: Your repair notice : Email subject}
        {--body= : Override the reply body (plain text; default is a realistic multi-paragraph reply)}';

    protected $description = 'Simulate an inbound landlord reply via a signed in-process webhook (local/staging/preprod only)';

    public function handle(): int
    {
        if (! app()->environment('local', 'staging', 'preprod')) {
            $this->error('dev:reply is restricted to the local, staging, and preprod environments.');

            return self::FAILURE;
        }

        [$case, $token] = $this->resolveCaseAndToken();
        if ($case === null) {
            return self::FAILURE;
        }

        $signingKey = (string) config('services.mailgun.webhook_signing_key');
        if ($signingKey === '') {
            $signingKey = self::DEV_SIGNING_KEY;
            config(['services.mailgun.webhook_signing_key' => $signingKey]);
        }

        $from = $this->option('from') ?? $case->requireLandlordRecipient()->email;
        $domain = (string) config('services.mailgun.inbound_domain');

        [$bodyHtml, $bodyPlain] = $this->resolveBody($case->requireLandlordRecipient()->role);

        $timestamp = (string) time();
        $nonce = Str::random(50);
        $payload = [
            'recipient' => "{$token}@{$domain}",
            'sender' => $from,
            'from' => $from,
            'subject' => $this->option('subject'),
            'body-html' => $bodyHtml,
            'body-plain' => $bodyPlain,
            'X-Mailgun-Spf' => 'Pass',
            'X-Mailgun-Dkim-Check-Result' => 'Pass',
            'Message-Id' => '<'.Str::random(24).'@'.$domain.'>',
            'timestamp' => $timestamp,
            'token' => $nonce,
            'signature' => hash_hmac('sha256', $timestamp.$nonce, $signingKey),
        ];

        $kernel = app(Kernel::class);
        $request = Request::create(route('webhooks.mailgun.inbound'), 'POST', $payload);
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $kernel->terminate($request, $response);

        $this->line("http_status={$status}");

        if ($status !== 200) {
            $this->error('Webhook did not return 200 — the reply was not processed.');

            return self::FAILURE;
        }

        $message = $case->fresh()->messages()
            ->where('direction', MessageDirection::Inbound)
            ->latest('id')
            ->first();

        $case->refresh();
        $this->line(
            'reply_received message_id='.($message?->id ?? 'none')
            .' quarantine='.($message?->quarantine_reason ?? 'none')
            ." from={$from} case={$case->url_slug} case_status={$case->status->value}"
        );

        return self::SUCCESS;
    }

    /**
     * Build the reply body as [html, plain].
     *
     * An explicit --body overrides both parts (the plain text is also wrapped
     * to minimal HTML). Otherwise a realistic multi-paragraph reply is used,
     * with the sign-off tailored to landlord vs agent. Sending an HTML part is
     * what makes HandleInboundReply populate body_sanitised via Purifier, so
     * the demo exercises the production (sanitised-HTML) render path.
     *
     * @return array{0:string,1:string}
     */
    private function resolveBody(LandlordContactRole $role): array
    {
        $override = $this->option('body');
        if ($override !== null && $override !== '') {
            return ['<p>'.nl2br(e($override)).'</p>', $override];
        }

        $signOff = $role === LandlordContactRole::Agent ? 'Lettings Team' : 'The Landlord';

        $paragraphs = [
            'Dear Sir/Madam,',
            'Thank you for your letter regarding the repairs at the property. I am sorry for the inconvenience this has caused.',
            'I have contacted my usual contractor and will arrange for them to inspect the issue within the next seven days. I will confirm a date with you shortly and make sure the necessary work is carried out promptly.',
            'Please do not hesitate to get in touch if you need anything further in the meantime.',
        ];

        $html = collect($paragraphs)->map(fn ($p) => '<p>'.e($p).'</p>')->implode('')
            .'<p>Kind regards,<br>'.e($signOff).'</p>';
        $plain = implode("\n\n", $paragraphs)."\n\nKind regards,\n{$signOff}";

        return [$html, $plain];
    }

    /**
     * @return array{0: ?RepairCase, 1: ?string} the case and its active reply token value
     */
    private function resolveCaseAndToken(): array
    {
        $opt = $this->option('case');

        if ($opt === null) {
            $token = ReplyToken::query()
                ->whereNull('superseded_at')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->orderByDesc('id')
                ->first();

            if ($token === null) {
                $this->error('No case with an active reply token. Run `php artisan dev:letter` first.');

                return [null, null];
            }

            return [$token->case, $token->token];
        }

        $case = is_numeric($opt)
            ? RepairCase::find((int) $opt)
            : RepairCase::where('url_slug', $opt)->first();

        if ($case === null) {
            $this->error("No case found for --case={$opt}.");

            return [null, null];
        }

        $token = $case->replyTokens()
            ->whereNull('superseded_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->orderByDesc('id')
            ->first();

        if ($token === null) {
            $this->error("Case {$case->url_slug} has no active reply token. Run `php artisan dev:letter` first.");

            return [null, null];
        }

        return [$case, $token->token];
    }
}

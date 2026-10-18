<?php

namespace App\Listeners;

use App\Mail\Notifications\WelcomeRegistered;
use App\Models\LetterTemplate;
use App\Services\LetterTemplateRenderer;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * #81 — send the welcome email when a user verifies their address.
 *
 * On Verified, not Registered (agreed 18 Oct 2026): an unverified account
 * cannot do anything, so it is not thanked, and a mistyped address never
 * receives it. Verified fires only on the first successful verification,
 * so the email goes once.
 *
 * Active-row idiom: no active welcome_registered row → no send, silently.
 */
class SendWelcomeEmail
{
    public function __construct(private LetterTemplateRenderer $renderer) {}

    public function handle(Verified $event): void
    {
        $template = LetterTemplate::query()
            ->where('type', 'account')
            ->where('code', 'welcome_registered')
            ->where('active', true)
            ->first();
        if ($template === null) {
            return;
        }

        $user = $event->user;

        $rendered = $this->renderer->render($template, [
            'tenant_name' => $user->name,
            'start_case_url' => route('cases.create'),
        ]);

        // Best-effort. The queue is sync, so this sends inside the
        // verification request — a mail failure must not cost the user
        // their verification or sign-in.
        try {
            Mail::to($user->email)->queue(new WelcomeRegistered(
                renderedSubject: $rendered['subject'],
                renderedBody: $rendered['body'],
            ));
        } catch (Throwable $e) {
            report($e);
        }
    }
}

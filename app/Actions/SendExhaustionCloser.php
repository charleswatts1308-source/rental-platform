<?php

namespace App\Actions;

use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\CaseNotice;
use App\Models\CaseMessage;
use App\Models\LetterTemplate;
use App\Models\PropertyLandlordContact;
use App\Models\RepairCase;
use App\Models\ReplyToken;
use App\Models\Setting;
use App\Services\LetterTemplateRenderer;
use App\Services\ReplyTokenGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * D14 (Phase 4) — the one-shot closing letter to the landlord at the
 * escalation_exhausted transition (design doc D5).
 *
 * "The last, strongest piece of the evidential trail and the landlord's
 * final unmistakable chance to engage before the platform goes quiet."
 *
 * Active-row idiom (D5): if an active `exhaustion_landlord` template exists,
 * render and send; else skip silently (return null). The template row is the
 * switch.
 *
 * Distinct from SendCaseNotice's escalation sends:
 *   - template type `exhaustion_landlord`, not `escalation`;
 *   - `stage_at_send = NULL` so the D3 counter predicate (outbound system
 *     rows with non-null stage_at_send) does NOT count it — the closer is
 *     not a ladder rung, and counting it would inflate the counter;
 *   - the silence clock is NOT restarted — the case is going terminal
 *     (NO_CLOCK). "No further automatic letters, ever";
 *   - it does NOT transition the case — SilenceSweep::executeExhaustionTransition
 *     owns the transitionTo(EscalationExhausted) around this call.
 *
 * A fresh reply token is minted (old superseded) so a late landlord reply
 * still routes home and revives the case (D14 allow-reply, HandleInboundReply
 * path A). The token carries no expiry — revival is never time-bounded.
 *
 * The body + subject are FROZEN on the case_messages row at send time and
 * CaseNotice reads them verbatim (the evidential invariant).
 */
class SendExhaustionCloser
{
    public function __construct(
        private ReplyTokenGenerator $tokenGenerator,
        private LetterTemplateRenderer $renderer,
    ) {}

    /**
     * @return CaseMessage|null the frozen closer message, or null when no
     *                          active exhaustion_landlord template exists
     */
    public function execute(RepairCase $case): ?CaseMessage
    {
        $template = LetterTemplate::firstActiveOfType('exhaustion_landlord');
        if ($template === null) {
            return null;
        }

        return DB::transaction(function () use ($case, $template) {
            // Same rule as SendCaseNotice: the property's CURRENT contact,
            // resolved once so binding, freeze and envelope agree.
            $recipient = $case->requireLandlordRecipient();

            $oldToken = $case->replyTokens()->whereNull('superseded_at')->first();
            if ($oldToken) {
                $oldToken->update([
                    'superseded_at' => now(),
                    'expires_at' => now()->addDays(90),
                ]);
            }

            $newToken = ReplyToken::create([
                'case_id' => $case->id,
                'token' => $this->tokenGenerator->generate(),
                'bound_email' => $recipient->email,
                'issued_at' => now(),
            ]);

            $message = $case->messages()->create([
                'direction' => MessageDirection::Outbound,
                'sender_role' => SenderRole::System,
                'stage_at_send' => null,
                'subject' => null,
                'body_raw' => '',
                'to_address_raw' => $recipient->email,
                'sent_at' => now(),
            ]);

            $caseForVars = $case->fresh()->load(['tenant', 'property']);
            $rendered = $this->renderer->render($template, $this->buildVars($caseForVars, $recipient));

            $message->update([
                'letter_template_id' => $template->id,
                'letter_template_updated_at' => $template->updated_at,
                'subject' => $rendered['subject'],
                'body_raw' => $rendered['body'],
            ]);

            Mail::to($recipient->email)->queue(new CaseNotice(
                $caseForVars,
                $message->fresh(),
                $newToken,
            ));

            $case->events()->create([
                'event_type' => 'exhaustion_closer_sent',
                'actor_label' => 'system',
                'occurred_at' => now(),
                'meta' => ['message_id' => $message->id, 'token_id' => $newToken->id],
            ]);

            if ($oldToken) {
                $case->events()->create([
                    'event_type' => 'token_superseded',
                    'actor_label' => 'system',
                    'occurred_at' => now(),
                    'meta' => ['token_id' => $oldToken->id],
                ]);
            }

            return $message->fresh();
        });
    }

    /**
     * @return array<string, string|int|null>
     */
    private function buildVars(RepairCase $case, PropertyLandlordContact $recipient): array
    {
        return [
            'tenant_name' => $case->tenant->name,
            'landlord_name' => $recipient->name ?: 'Sir or Madam',
            'case_reference' => $case->url_slug,
            'property_address' => $this->propertyAddress($case),
            'issue_description' => $case->description,
            'response_days' => (int) Setting::get('escalation.interval_days', 14),
            'notice_number' => null,
            'deadline_date' => null,
        ];
    }

    private function propertyAddress(RepairCase $case): string
    {
        $p = $case->property;

        return implode(', ', array_filter([
            $p->address_line1,
            $p->address_line2,
            $p->city,
            $p->postcode,
        ]));
    }
}

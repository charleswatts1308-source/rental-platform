<?php

namespace App\Actions;

use App\Enums\CaseStatus;
use App\Enums\MessageDirection;
use App\Enums\SenderRole;
use App\Mail\Notifications\LandlordReplyReceived;
use App\Models\CaseMessage;
use App\Models\ReplyToken;
use App\Services\Silence\SilenceClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Mews\Purifier\Facades\Purifier;

/**
 * Handles a Mailgun-forwarded inbound email payload.
 *
 * The signature has already been verified by VerifyMailgunSignature
 * middleware before this action runs. This action's job is to:
 *
 *   1. Extract the reply token from the recipient address.
 *   2. Resolve the token to a case (rejecting unknown/expired tokens
 *      silently — the caller still returns 200 so we don't leak token
 *      validity to attackers via timing or bounce).
 *   3. Compose an inbound case_message with body_raw + body_sanitised.
 *   4. Apply sender-mismatch quarantine when the From address doesn't
 *      match the case's landlord_contact email (case-insensitive,
 *      +suffix tolerant).
 *   5. Increment the reply_token use_count and update last_used_at.
 *   6. Either transition the case (awaiting_landlord, on_hold, or
 *      dormant → awaiting_tenant_review, with the canonical event
 *      written by transitionTo as inbound_received or
 *      inbound_quarantined depending on quarantine status) or, when
 *      no transition applies (already-in-review, awaiting-action, or
 *      terminal), write the inbound event manually without changing
 *      status.
 *
 * Returns the created CaseMessage on success, or null when the inbound
 * was silently dropped (unknown/expired/malformed token).
 */
class HandleInboundReply
{
    private const STATES_THAT_TRANSITION = [
        CaseStatus::AwaitingLandlord,
        CaseStatus::OnHold,
        CaseStatus::Dormant,
    ];

    public function execute(array $payload): ?CaseMessage
    {
        $recipient = (string) ($payload['recipient'] ?? '');
        $tokenValue = $this->extractToken($recipient);

        if ($tokenValue === null) {
            Log::info('Inbound webhook: malformed recipient', ['recipient' => $recipient]);

            return null;
        }

        $replyToken = ReplyToken::where('token', $tokenValue)->first();

        if ($replyToken === null) {
            Log::info('Inbound webhook: unknown token');

            return null;
        }

        if ($replyToken->expires_at !== null && $replyToken->expires_at->isPast()) {
            Log::info('Inbound webhook: expired token', ['token_id' => $replyToken->id]);

            return null;
        }

        $case = $replyToken->case;

        $message = DB::transaction(function () use ($payload, $case, $replyToken) {
            $bodyHtml = (string) ($payload['body-html'] ?? '');
            $bodyPlain = (string) ($payload['body-plain'] ?? '');
            $bodyRaw = $bodyHtml !== '' ? $bodyHtml : $bodyPlain;
            $bodySanitised = $bodyHtml !== '' ? Purifier::clean($bodyHtml) : null;

            $fromRaw = (string) ($payload['from'] ?? '');
            $quarantineReason = $this->resolveQuarantineReason($fromRaw, $case->landlordContact->email);

            $message = $case->messages()->create([
                'direction' => MessageDirection::Inbound,
                'sender_role' => SenderRole::Landlord,
                'subject' => $payload['subject'] ?? null,
                'body_raw' => $bodyRaw,
                'body_sanitised' => $bodySanitised,
                'from_address_raw' => $fromRaw,
                'to_address_raw' => $payload['recipient'] ?? null,
                'spf_pass' => $this->boolFromMailgunFlag($payload['X-Mailgun-Spf'] ?? null),
                'dkim_pass' => $this->boolFromMailgunFlag($payload['X-Mailgun-Dkim-Check-Result'] ?? null),
                'mailgun_message_id' => $this->normalizeMessageId($payload['Message-Id'] ?? null),
                'quarantine_reason' => $quarantineReason,
                'received_at' => now(),
            ]);

            $replyToken->increment('use_count');
            $replyToken->last_used_at = now();
            $replyToken->save();

            // Silence-model clock flip (Phase 2a). A landlord-direction
            // message landed; ball flips to tenant; clock restarts with
            // a fresh settings snapshot per D6 + D4. Quarantine status is
            // irrelevant here — the landlord engaged, and the tenant gets
            // notified, so the silence model treats the message as
            // having arrived. Pure column writes; old code reads none of
            // these. Zero behaviour change.
            $case->ball_with = 'tenant';
            $case->silence_clock_started_at = now();
            $case->silence_settings_snapshot = SilenceClock::snapshotCurrentSettings();

            $eventType = $quarantineReason !== null ? 'inbound_quarantined' : 'inbound_received';

            if (in_array($case->status, self::STATES_THAT_TRANSITION, true)) {
                // transitionTo's save() persists the clock attributes
                // alongside the status change.
                $case->transitionTo(CaseStatus::AwaitingTenantReview, [
                    'actor_label' => 'system',
                    'event_type_override' => $eventType,
                    'meta' => ['message_id' => $message->id],
                ]);
            } else {
                // No transition — but the clock-start columns still need
                // to be persisted. Status is not dirty, so the booted
                // updating listener's direct-write check is satisfied.
                $case->save();
                $case->events()->create([
                    'event_type' => $eventType,
                    'actor_label' => 'system',
                    'occurred_at' => now(),
                    'meta' => [
                        'message_id' => $message->id,
                        'case_status' => $case->status->value,
                    ],
                ]);
            }

            return $message;
        });

        Mail::to($case->tenant->email)->queue(new LandlordReplyReceived($case));

        return $message;
    }

    private function extractToken(string $recipient): ?string
    {
        if (preg_match('/^([A-Za-z0-9]{20})@/', $recipient, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function resolveQuarantineReason(string $rawFrom, string $expectedEmail): ?string
    {
        $extracted = $this->extractEmail($rawFrom);

        if ($extracted === null) {
            return 'unexpected_from_address';
        }

        return $this->emailsMatch($extracted, $expectedEmail) ? null : 'unexpected_from_address';
    }

    private function extractEmail(string $rawFrom): ?string
    {
        $candidate = preg_match('/<([^>]+)>/', $rawFrom, $matches) === 1
            ? trim($matches[1])
            : trim($rawFrom);

        return filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false ? strtolower($candidate) : null;
    }

    private function emailsMatch(string $a, string $b): bool
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));

        if (! str_contains($a, '@') || ! str_contains($b, '@')) {
            return false;
        }

        [$aLocal, $aDomain] = explode('@', $a, 2);
        [$bLocal, $bDomain] = explode('@', $b, 2);

        if ($aDomain !== $bDomain) {
            return false;
        }

        $aBase = explode('+', $aLocal, 2)[0];
        $bBase = explode('+', $bLocal, 2)[0];

        return $aBase === $bBase;
    }

    private function normalizeMessageId(?string $rawId): ?string
    {
        if ($rawId === null || $rawId === '') {
            return null;
        }

        return trim($rawId, '<> ');
    }

    private function boolFromMailgunFlag(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return strcasecmp((string) $value, 'Pass') === 0;
    }
}

<?php

namespace App\Services;

use App\Models\MagicLoginToken;
use App\Models\RepairCase;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * D12 — mints magic-login tokens and builds the signed URLs that
 * tenant-bound outbound emails carry. Used by SilenceSweep,
 * SendCaseNotice (tenant notification), and HandleInboundReply
 * (landlord reply notification).
 *
 * Expiry is 7 days, hardcoded per D0.7 ruling. Single-use is
 * enforced at consume time in MagicLinkController.
 *
 * The URL is signed via Laravel's signed-route middleware
 * (URL::temporarySignedRoute) — the signature is the bearer; the
 * database row gives the single-use + auth-target attributes.
 */
class MagicLinkGenerator
{
    private const EXPIRY_DAYS = 7;

    /**
     * @param  'case_reply'|'dormancy_nudge'|'auto_escalation'|'landlord_reply_received'|'hold_expired'|'dormancy_transition'|'tenant_exhaustion'  $purpose
     */
    public function mint(User $user, ?RepairCase $case, string $purpose): MagicLoginToken
    {
        $expiresAt = now()->addDays(self::EXPIRY_DAYS);

        return MagicLoginToken::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'purpose' => $purpose,
            'case_id' => $case?->id,
            'expires_at' => $expiresAt,
        ]);
    }

    public function url(MagicLoginToken $token): string
    {
        return URL::temporarySignedRoute(
            'magic-link.consume',
            $token->expires_at,
            ['token' => $token->token],
        );
    }

    /**
     * Convenience helper: mint + build URL in one call.
     */
    public function mintUrl(User $user, ?RepairCase $case, string $purpose): string
    {
        return $this->url($this->mint($user, $case, $purpose));
    }
}

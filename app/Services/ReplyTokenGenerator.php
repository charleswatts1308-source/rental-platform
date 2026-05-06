<?php

namespace App\Services;

use App\Models\ReplyToken;
use Illuminate\Support\Str;
use RuntimeException;

class ReplyTokenGenerator
{
    private const TOKEN_LENGTH = 20;

    private const MAX_RETRIES = 5;

    /**
     * Generate a cryptographically random 20-character base62 token
     * (~119 bits entropy) that does not collide with an existing
     * reply_tokens.token. Retries up to MAX_RETRIES times before
     * throwing.
     */
    public function generate(): string
    {
        for ($i = 0; $i < self::MAX_RETRIES; $i++) {
            $candidate = Str::random(self::TOKEN_LENGTH);

            if (! ReplyToken::where('token', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'Could not generate a unique reply token after '.self::MAX_RETRIES.' attempts'
        );
    }
}

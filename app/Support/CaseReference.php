<?php

namespace App\Support;

/**
 * D16 / #4 — human-quotable case reference.
 *
 * 6 characters from a 32-symbol read-aloud-safe alphabet: A–Z and 2–9 with
 * the ambiguous glyphs removed (I, O, 0, 1). ~1.07bn combinations. Because
 * digits are always in the alphabet no token reads as a word, so no
 * profanity/near-word filter is needed.
 *
 * Security is NOT carried by the reference — per-tenant login + the
 * `view` policy gate case access — so a short, quotable value weakens
 * nothing (design doc D16 #4). Random (not sequential) so it leaks neither
 * case volume nor allows walking. Uniqueness is enforced by the caller's
 * collision loop (see CaseController::mintSlug).
 */
class CaseReference
{
    /** 24 letters (A–Z minus I, O) + 8 digits (2–9) = 32 symbols. */
    public const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public const LENGTH = 6;

    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $reference = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $reference .= self::ALPHABET[random_int(0, $max)];
        }

        return $reference;
    }
}

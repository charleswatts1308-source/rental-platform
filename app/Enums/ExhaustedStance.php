<?php

namespace App\Enums;

/**
 * D14 — the tenant's framing of an escalation_exhausted case. COSMETIC: a
 * displayed label and nothing more. It is read by the UI and by NOTHING in
 * the sweep, verdict, clock, or state machine — it cannot change state,
 * ball, clock, or sweep behaviour. Three positions, with "unset" modelled
 * as a NULL column (the tenant is never forced to choose):
 *
 *   null        — not framed.
 *   Abandoned   — the tenant considers the matter given up on.
 *   Unresolved  — the tenant considers the matter still open / unsettled.
 *
 * Deliberately NOT the CaseStatus::Abandoned terminal — that closes the
 * case; this only labels an already-exhausted one.
 */
enum ExhaustedStance: string
{
    case Abandoned = 'abandoned';
    case Unresolved = 'unresolved';

    public function label(): string
    {
        return match ($this) {
            self::Abandoned => 'Abandoned',
            self::Unresolved => 'Unresolved',
        };
    }
}

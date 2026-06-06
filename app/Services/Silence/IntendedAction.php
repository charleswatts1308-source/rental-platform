<?php

namespace App\Services\Silence;

/**
 * Fixed vocabulary of silence-model sweep decisions. Maps 1:1 to the
 * intended_action column on silence_shadow_log.
 *
 * In shadow mode (Phase 2a) all these are LOGGED intents only — no
 * letters fire, no transitions happen. Phase 2b makes them real and
 * demolishes the old ladder/sweep.
 */
enum IntendedAction: string
{
    case NoAction = 'no_action';
    case SendEscalation = 'send_escalation';
    case SendNudge = 'send_nudge';

    // Phase 4 builds the escalation_exhausted state. Until then the
    // shadow sweep logs the intent as a marker — never transitions.
    case TransitionExhaustedIntent = 'transition_exhausted_intent';
    case TransitionDormantIntent = 'transition_dormant_intent';
}

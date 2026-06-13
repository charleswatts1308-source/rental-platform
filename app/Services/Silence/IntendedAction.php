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

    // D15 — engagement-gated escalation. An engaged-then-quiet landlord
    // case WITHHOLDS the escalation send and surfaces the prepared notice
    // for tenant authorisation. This verdict means: do not auto-send;
    // instead fire a tenant-facing authorise-nudge pointing at the
    // authorise action. Landlord-ball throughout (ruling 2). Mail-only,
    // never a case_messages row (same evidential invariant as nudges).
    case SendAuthorisationNudge = 'send_authorisation_nudge';

    // Phase 4 builds the escalation_exhausted state. Until then the
    // shadow sweep logs the intent as a marker — never transitions.
    case TransitionExhaustedIntent = 'transition_exhausted_intent';

    // Phase 3 dormancy goes LIVE: this is no longer an intent marker —
    // silence:sweep executes a real transitionTo(Dormant) and queues
    // the dormancy_transition_notice mail.
    case TransitionDormantIntent = 'transition_dormant_intent';

    // Phase 3 — absorbs the demolished SweepHolds command. On_hold
    // cases past hold_until transition straight to AwaitingLandlord
    // (the landlord still owes a response — no TAR intermediate).
    case ResumeFromHold = 'resume_from_hold';
}

# CC BRIEF — Silence Model, Phase 2a: Clock in Shadow Mode

**Read first:** `docs/llcs-silence-model-design.md`
(design doc — authoritative for what and why; if this brief conflicts
with it, the design doc wins, flag the conflict) and your own Phase 1
implementation as merged at a775ad2.

**Discipline:** report first, edit second. Deliverable 0 (below) before
any code changes.

---

## Goal

Build the silence-model clock — turn detection, deadline computation,
the scheduler decision logic from design doc §6 — and run it in
**shadow mode**: on every sweep it records what it WOULD do, and does
nothing else. No sends, no state transitions, no case mutations beyond
its own shadow log. The old model (SweepEscalations, SweepDormancy,
SweepHolds, tenant-click escalation, `next_stage_eligible_at`) remains
fully in charge and fully intact.

Purpose: we verify the new model's decisions against real lifecycle
data on gafol for a while, by reading its log, before Phase 2b makes it
live and demolishes the ladder. A wrong decision in shadow mode costs
nothing.

**Zero behaviour change** (again): the existing test baseline (396 /
887) stays green and unweakened. The only observable additions are the
shadow log and the settings reads listed below.

---

## Git

- Branch `silence-phase-2a` off main (a775ad2 or later).
- No commits to main. `--no-ff` merge after review + green suite +
  shadow-log verification on gafol.

---

## Deliverable 0 — Report (before any edits)

1. **Turn detection design.** Per design doc §2 (D2) and §6, the clock
   needs "whose turn is it" per case. Report how you'll derive ball
   position from existing data (`case_messages` direction + timestamps,
   current status), and enumerate the mapping for EVERY current
   CaseStatus value: for each status — clock runs against landlord /
   clock runs against tenant / no clock (terminal or excluded). Flag
   any status where the answer is genuinely ambiguous rather than
   guessing. Note: `on_hold` = no clock (design doc D2 — explicit
   tenant pause).
2. **Shadow log shape.** Propose the storage (suggest: a
   `silence_shadow_log` table — case_id, swept_at, ball_with,
   silence_days, intended_action, intended_letter_template_id,
   escalation_counter_value, reasoning string). One row per case per
   sweep where the new model would ACT; a summary row or count for
   no-action sweeps is acceptable — propose the balance between
   completeness and table bloat.
3. **Escalation counter derivation.** D3: counter = count of escalation
   letters already sent, derived from `case_messages`. Report the exact
   query/predicate you'll use (template type? stage_at_send?
   letter_template_id join?) and confirm it gives the right answer for
   lifecycle-seeded cases — including legacy rows that predate
   letter_templates (template_key only). The dual-write from Phase 1
   matters here.
4. **Sweep job placement.** Report whether the shadow evaluation runs
   as a new artisan command + scheduler entry, or inside the existing
   sweep jobs. Recommend: NEW command (`silence:sweep --shadow` or
   similar), scheduled independently — the old sweeps must not gain
   logic, and 2b's cutover then = pointing the new command live +
   deleting old sweeps, not untangling merged code.
5. **Settings reads.** Phase 1 seeded settings with no readers. The new
   clock logic reads `escalation.interval_days`,
   `escalation.max_notices`, and the three `nudge.*` keys. Report the
   read mechanism (direct Setting model query per sweep is fine —
   premature caching not wanted) and where `{{response_days}}` in
   `SendCaseNotice::buildLetterVars()` swaps from hardcoded 14 to the
   setting read — per design doc D4 this swap is wording-neutral
   (both say 14).
6. **D4 in-flight guardrail.** Design doc §2 D4: a settings change
   applies only to clocks started after the change. Report how the
   shadow model honours this — what is stored per case at "clock
   start", and what the deadline computation reads. This must be
   testable; name the tests you'll write for it.

Stop after Deliverable 0 and wait for go-ahead.

---

## Scope — build

1. **Clock fields** on `cases` (migration): per your D0.6 proposal —
   indicatively `silence_clock_started_at`, `silence_deadline_at`,
   `silence_interval_days_in_force`, `ball_with` — exact shape is your
   call within the D4 guardrail, justified in D0.
2. **Shadow log table** per your D0.2 proposal.
3. **Turn detection** per your D0.1 mapping.
4. **The sweep decision logic** (design doc §6) in shadow form:
   - expired clock + ball with landlord + counter < max_notices →
     intended_action = send escalation (notice N+1, template resolved
     via the D1 fallback lookup — record which template row)
   - expired clock + ball with landlord + counter ≥ max_notices →
     intended_action = transition to escalation_exhausted (the state
     doesn't exist yet — log the intent as a string; Phase 4 builds it)
   - expired clock + ball with tenant → intended_action = next nudge
     per the nudge ladder, or transition to dormant when the ladder is
     exhausted (which nudge number, from what derivation — nudges
     aren't sent yet so there's no send history; propose the
     derivation in D0.2, e.g. from shadow log itself or silence
     duration vs the three nudge thresholds)
   - no expired clock → no action
5. **Clock lifecycle wiring, shadow-safe:** the clock fields must be
   SET by real events to be observable — on outbound letter send
   (SendCaseNotice) and on inbound landlord reply (HandleInboundReply),
   set/flip ball_with and restart the clock per D2/D6. These writes are
   new columns only — no existing behaviour reads them in 2a, so the
   old model is unaffected. Flag in your report if any of these touch
   points would alter existing behaviour in ANY way.
6. **Settings reads** live (clock logic + the `{{response_days}}`
   source swap).
7. **`silence:sweep` artisan command** (shadow-only — refuses to do
   anything but log in this phase) + scheduler registration alongside
   the existing sweeps. The command takes a `--pretend-today=YYYY-MM-DD`
   option (dev/staging environments only — same env allow-list as the
   dev:* commands) that evaluates all clocks as if today were that
   date, writing shadow rows flagged as pretend (column or marker in
   the log so pretend rows are never confused with real sweeps). This
   is the time-travel lever for exploratory testing: lifecycle-seeded
   cases all have fresh timestamps, so without it every real sweep
   honestly reports no-action. Internally, prefer accepting "now" as
   an injected parameter throughout the clock/decision logic rather
   than branching on the flag — that makes time a test input
   everywhere (Carbon::setTestNow or explicit clock parameter), which
   the unit tests need anyway.
8. **A read view of the shadow log** for verification: artisan command
   is enough (`silence:shadow-report` listing recent intended actions
   per case, human-readable). No UI.

## Scope — explicitly untouched

- SweepEscalations / SweepDormancy / SweepHolds — not modified, not
  disabled, gain no logic.
- `templateKeyForStage()`, `nextStageEligibleAt()`, dual-write — all
  still in place; they die in 2b.
- State machine: no new states, no new transitions, no changes to
  transitionTo or CaseStatus. (`escalation_exhausted` is Phase 4.)
- Tenant notification mailables — untouched (their mapping to nudge
  templates is decided at 2b/Phase 4).
- No sends of any kind originate from the new code.
- Dead stage Blade views — still not deleted (2b demolition).

## Tests

- Baseline 396 stays green, unweakened.
- New coverage, minimum: turn-detection mapping (every status),
  deadline computation incl. D6 restart-on-tenant-message, D4
  in-flight guardrail (the named tests from D0.6), counter derivation
  (incl. legacy template_key-only rows), each sweep decision branch
  (escalate / exhaust-intent / nudge / dormant-intent / no-action),
  shadow mode sends nothing and transitions nothing (assert no mail
  queued, no status change across a sweep), settings reads, fallback
  template resolution recorded in the log.

## Acceptance

1. Suite green, ≥ 396, no weakened assertions (enumerate any wording/
   contract changes as before).
2. On gafol after deploy: `dev:lifecycle`, then `silence:sweep` (real —
   expect mostly no-action on fresh data), then sweeps with
   `--pretend-today` at +15, +25, +35 days, then `silence:shadow-report`
   shows a defensible intended action (or no-action) for each of the 8
   cases at each horizon, consistent with the design doc — we will
   review this output together as the phase gate.
3. Diff shows old sweep jobs and state machine untouched.
4. Settings change (e.g. interval 14→7 via phpMyAdmin) demonstrably
   affects only clocks started afterwards (show via shadow log or
   test).

## Out of scope

Everything in "explicitly untouched", plus: admin UI (Phase 5), tenant
reply UI (Phase 3), escalation_exhausted state (Phase 4), delivery-
status webhooks (snag #8, post-cutover).

# CC BRIEF — Silence Model, Phase 2b: Landlord-Side Cutover + Demolition

**Read first:** the design doc (`docs/llcs-silence-model-design-*.md` —
authoritative; conflicts → design doc wins, flag them), the Phase 2a
brief, and your own 2a implementation as merged at 0f0aabf.

**Discipline:** report first, edit second. Deliverable 0 before any
code changes. This is the phase where the report-first rule earns its
keep — much of D0 is enumeration of what breaks and what dies.

---

## Goal

The silence model takes over the **landlord side** for real: landlord
silence automatically fires escalation letters via `silence:sweep`, and
the old ladder machinery that did this job (tenant-prompted, via
SweepEscalations + tenant click) is demolished.

The **tenant side stays in shadow.** Nudges and dormancy transitions
continue to be logged as intents only. Rationale (ruling, not yours to
revisit): a live nudge tells the tenant to act, but the tenant reply
action doesn't exist until Phase 3 — nudging toward a missing button,
then dormancy-ing the tenant for not pressing it, breaks the design
doc's "explained, recoverable sequence" promise. Tenant-side goes live
with Phase 3.

Consequence: SweepDormancy and SweepHolds (tenant-side old machinery)
survive 2b unchanged and die at Phase 3 cutover. SweepEscalations dies
now.

## What changes for a real case after 2b

- Landlord goes silent past the interval → the sweep sends the next
  escalation letter automatically (ratchet counter, D1 fallback
  template lookup, evidence frozen — the full Phase 1 path), restarts
  the clock, and notifies the tenant that a letter went out in their
  name.
- At counter ≥ max_notices: NO further sends. Log
  transition_exhausted_intent only — the escalation_exhausted state is
  Phase 4; until then the case simply stops generating letters. Never
  re-fire the final notice (design doc D5 rejected option b).
- The tenant's "send next notice" click (CaseController::sendNextNotice)
  REMAINS as tenant-initiated escalation — it covers the
  landlord-replied-but-refused path, which silence detection by
  definition never escalates. Flagged in the design doc as open
  question D7; resolved at Phase 3. Do not demolish it.
- Everything else a tenant or landlord sees is unchanged.

---

## Git

- Branch `silence-phase-2b` off main (0f0aabf or later).
- No commits to main. `--no-ff` merge after review + green suite +
  live-fire verification on gafol.

---

## Deliverable 0 — Report (before any edits)

1. **Test-break enumeration — the headline deliverable.** Run the
   analysis (not the edits): list every existing test that the cutover
   breaks, and for each give its disposition: (a) rewritten to assert
   the new behaviour — state what it will assert; (b) deleted because
   the behaviour it pins is demolished — state which demolition item
   kills it; (c) unaffected-but-touched (e.g. setup uses a demolished
   helper). Weakened assertions are not a disposition. This list is
   reviewed before go-ahead.
2. **Demolition enumeration.** Every file, class, method, route,
   schedule entry, mailable, view, and column write that the old
   landlord-side model comprises. Known members (verify and complete
   the list, don't assume it's exhaustive): SweepEscalations command +
   schedule entry; EscalationEligible mailable + view;
   `nextStageEligibleAt()` and all `next_stage_eligible_at`
   reads/writes (report whether the COLUMN can drop now or carries
   data worth keeping); the four dead stage Blade views (deletion
   promised since Phase 1); the awaiting_landlord →
   tenant_action_required transition IF SweepEscalations was its only
   driver (report what else, if anything, fires it).
3. **template_key disposition.** Enumerate remaining consumers of
   `case_messages.template_key` and the dual-write. Recommend: drop
   the dual-write and column in this phase's demolition, or defer with
   a reason. (The 2a counter predicate uses stage_at_send, not
   template_key, so the counter is not a consumer.)
4. **SendCaseNotice guard + transition changes.** Auto-escalation
   fires while the case is in awaiting_landlord; the current guard
   (status must be Open or TenantActionRequired) and the transition
   map must accommodate it (self-transition, or send-without-
   transition — propose). Enumerate every guard/transition edit with
   its audit-trail (case_events) consequence. The tenant click path
   must keep working unchanged alongside.
5. **Tenant notification on auto-escalation.** Mechanics for "we've
   sent notice N to your landlord" — new template row (type
   tenant_notification, active-row idiom: no active row → no
   notification, send-point exists regardless), sent to the tenant's
   email on every sweep-fired escalation. Propose template code,
   placeholder needs, and where the send hangs in the sweep flow.
   NOTE: this is a tenant notification (app surface), not evidential
   correspondence — it must NOT create a case_messages row (the 2a
   ruling-d invariant; your own SilenceClock docblock).
6. **Live/shadow mechanics.** How silence:sweep distinguishes live
   actions (landlord-side) from shadow intents (tenant-side) — and the
   hard rule: `--pretend-today` ALWAYS forces full shadow for
   everything, regardless of side. Pretend must never send. Propose
   how the shadow log records live-executed actions (e.g. an
   `executed` flag) so the log remains the complete audit of sweep
   decisions.
7. **dev tooling impact.** Does dev:lifecycle build any state via
   to-be-demolished machinery (the "escalated" step)? Report needed
   changes so the 8-state seed still works post-cutover, and which
   states' recipes change (tenant_action_required now reachable only
   via hold expiry). Also propose a small clock-aging dev command
   (e.g. `dev:age-clock --case= --days=`, dev/staging/preprod only)
   so live-fire testing doesn't require phpMyAdmin date surgery.
8. **Idempotency / double-fire safety.** What prevents a sweep from
   firing the same escalation twice (crash mid-sweep, overlapping
   runs, manual + scheduled invocation in the same minute)? Propose
   the guard (e.g. clock restart inside the same transaction as the
   send; withoutOverlapping on the schedule entry).

Stop after Deliverable 0 and wait for go-ahead.

---

## Scope — build

1. silence:sweep goes live for landlord-side verdicts: send_escalation
   executes the real send path (template lookup with D1 fallback,
   render, freeze, token supersede/mint, case_messages row, queue,
   counter advances by virtue of the new row), restarts the clock +
   re-snapshots settings, notifies the tenant per D0.5, logs the
   shadow row marked executed.
2. exhausted-intent behaviour at counter ≥ max (log only, no send, no
   state change — Phase 4 takes it from there).
3. Tenant-side verdicts remain shadow (logged, not executed).
4. `--pretend-today` = full shadow always.
5. dev tooling updates per D0.7 incl. the clock-aging command.
6. Demolition per the approved D0.2/D0.3 enumeration.
7. Schedule: silence:sweep keeps its slot; SweepEscalations entry
   removed; SweepDormancy/SweepHolds entries untouched.

## Scope — explicitly untouched

- SweepDormancy, SweepHolds (die at Phase 3).
- CaseController::sendNextNotice and its UI (D7 — revisit Phase 3).
- Tenant notification mailables other than EscalationEligible
  (DormancyReminder, HoldExpired, LandlordReplyReceived stay).
- No new states; escalation_exhausted is Phase 4.
- Tenant reply UI — Phase 3.
- Admin UI — Phase 5.

## Tests

- Disposition per the approved D0.1 list; no weakened assertions;
  enumerate any deltas from the approved list in the implementation
  report.
- New coverage, minimum: sweep executes landlord-side sends end-to-end
  (mail queued, case_messages row created, clock restarted, snapshot
  refreshed, shadow row marked executed); tenant notification fires on
  auto-escalation and respects the active-row switch; no
  case_messages row from the tenant notification; counter ≥ max sends
  nothing; tenant-side verdicts still execute nothing; pretend mode
  executes nothing on either side; double-fire guard per D0.8; tenant
  click path still works and coexists with the sweep (click then
  sweep same day doesn't double-send); demolished paths are gone
  (routes/commands absent).

## Acceptance

1. Suite green; count reported against the post-disposition baseline
   (it may legitimately drop from 448 if deleted tests outnumber new —
   the disposition list is the reference, not the raw count).
2. Live fire on gafol, jointly reviewed: dev:lifecycle; age case 2's
   clock past the interval via the new dev command; real
   silence:sweep run; verify — escalation letter sent (Mailgun log
   as ground truth, Gmail best-effort), notice number 2 rendered via
   the generic template, case_messages row frozen with template ref,
   clock restarted, tenant notification received, shadow row marked
   executed. Then a second sweep same day: no double-send.
3. Pretend sweep at +35d: nothing sent, tenant-side intents still
   logged.
4. Diff confirms SweepDormancy/SweepHolds and sendNextNotice
   untouched, and every approved demolition item gone.

## Out of scope

Phase 3 (tenant reply, tenant-side go-live, SweepDormancy/SweepHolds
demolition, D7 resolution), Phase 4 (escalation_exhausted), Phase 5
(admin UI), snag #8 (delivery webhooks).

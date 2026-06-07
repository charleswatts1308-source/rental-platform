# CC BRIEF — Silence Model, Phase 3: Tenant Reply + Tenant-Side Go-Live

**Read first:** CLAUDE.md, the design doc
(`docs/llcs-silence-model-design.md` at 30a2032 or later — authoritative;
conflicts → design doc wins, flag them), this brief, and the Phase 2b
close-out write-up.

**Discipline:** report first, edit second. Deliverable 0 before any code
changes.

---

## Goal

The original half-duplex snag closes: the tenant gains a reply action
(D8). The tenant side of the silence machinery goes live: nudges send,
dormancy transitions happen (no more shadow-only). The old tenant-side
machinery (SweepDormancy, SweepHolds) and the tenant escalation click
(D7 resolved) are demolished. Supporting decisions land with it:
case description on every outbound mail (D9), explicit pause with
guardrails (D10), dormancy revival window (D11), magic-link sign-in
(D12), and the create-case preview + one-time authorisation (D13).

## What changes for a real case after Phase 3

- Tenant can reply from the case page per the D8 availability table.
  Reply → ball to landlord, clock restarts, message frozen in
  case_messages, sent via the outbound machinery with a fresh token.
- Tenant silence now has live consequences: nudge at 10 days, second
  nudge at 20, dormancy warning, dormant at 30 — all explained,
  recoverable by replying (within `dormancy.revival_days`).
- Tenant can pause the case until a date (≤ `hold.max_days`); expiry
  resumes it automatically.
- Every outbound email carries the standing header block: property +
  case reference + original description.
- Every tenant-bound email signs the tenant in on click (magic link).
- Creating a case now shows the rendered notice 1 for confirmation
  before sending, with the one-time authorisation wording.
- The "send next notice" button is gone. Escalation is sweep-only.

---

## Git

- Branch `silence-phase-3` off main (30a2032 or later). Tag
  `pre-silence-phase-3` on main before branching.
- No commits to main. `--no-ff` merge after review + green suite +
  live-fire verification on gafol.

---

## Deliverable 0 — Report (before any edits)

1. **Test-break enumeration — headline deliverable.** Every existing
   test the phase breaks, each with a disposition: (a) rewritten —
   state what it will assert; (b) deleted — state which demolition
   kills it; (c) unaffected-but-touched. Weakened assertions are not
   a disposition. Reviewed before go-ahead.
2. **Demolition enumeration.** Everything the old tenant-side model
   comprises. Known members (verify and complete): SweepDormancy +
   schedule entry; SweepHolds + schedule entry; CaseController::
   sendNext + route + UI button/view fragments; the
   escalation_confirmed_by_tenant event emission (report all
   emitters); DormancyReminder / HoldExpired mailables IF the sweep's
   template-table nudges replace them (report: replace or retain).
   Report which event vocabulary dies and which case_events
   consequences follow.
3. **tenant_action_required disposition.** With sendNext demolished,
   report what still drives a case INTO tenant_action_required, what
   the state means post-D7, and what the tenant can do there. Propose:
   survives with a purpose, or demolish (with transition-map and
   hold-expiry-target consequences). FLAG: this may need a design
   ruling — present the options, don't pick silently.
4. **Reply surface proposal.** Controller, route, validation, the D8
   availability gate (per-state), the transition wiring (all roads to
   awaiting_landlord), how the reply reuses SendCaseNotice or a
   sibling action (report: shared path vs parallel path, with the
   evidential-freeze and token-mint consequences), and the UI
   placement on cases/show.
5. **Sweep absorption.** silence:sweep takes over live tenant-side
   execution: nudge sends (template-table, active-row idiom),
   dormancy transition (real transitionTo now, not intent), hold
   expiry (SweepHolds' job — report how it folds in: sweep clause vs
   retained command). Enumerate verdict-to-execution changes vs 2b's
   landlord-only $shouldExecute gate. Pretend mode still executes
   NOTHING.
6. **Description plumbing (D9).** Migration for cases.description;
   backfill strategy for existing rows (gafol/dotrent have only seed
   data — report if a backfill is needed at all); the header-block
   render path into EVERY outbound mailable (enumerate them); the
   create-case form change; SendCaseNotice $tenantStatement parameter
   disposition now that description lives on the case; dev:lifecycle
   SPECS description column (snags #3/#11 close here).
7. **Magic-link mechanics (D12).** Token table shape, signed route +
   middleware, single-use enforcement, expiry (propose a value),
   which emails get linked (enumerate), and what happens on an
   expired/used link (fall back to login page, no error dead-end).
8. **Create-case preview (D13).** The form flow change: enter →
   preview rendered notice 1 → confirm → send. Where the
   authorisation copy lives (content row vs blade). What happens on
   back/edit.
9. **Settings additions.** dormancy.revival_days=90, hold.max_days=60
   — seeder rows, snapshot implications (do these join the per-case
   snapshot or read live? Report against the D4 in-flight guardrail).
10. **dev tooling impact.** Recipe changes for the 8-state seed
    (dormant/on_hold/TAR recipes), SPECS description column, whether
    dev:age-clock suffices for nudge/dormancy live-fire testing or a
    sibling is needed.
11. **Ride-along snags.** #1 (nav dropdown title — propose wording),
    #9 (silence_shadow_log into DevReset::TABLES), #10 (sweep summary
    tally fix — propose which of the two snag-listed fixes).
12. **Idempotency / double-fire.** The 2b guard covered escalation
    sends; report its extension to nudge sends and dormancy/hold
    transitions (same locked re-read pattern?).

Stop after Deliverable 0 and wait for go-ahead.

---

## Scope — build

1. Reply action per approved D0.4: UI, controller, gate, transition,
   freeze, token.
2. Tenant-side live execution per approved D0.5, including hold
   expiry absorption.
3. cases.description per approved D0.6, header block on all outbound
   mail, create-case + dev tooling changes.
4. on_hold pause UI: pause-until-date form, hold.max_days cap,
   button copy as content.
5. Dormancy revival window per D11: reply gate within
   dormancy.revival_days, "raise a new case" guidance beyond it.
6. Magic links per approved D0.7 on all touched emails.
7. Create-case preview + authorisation per approved D0.8.
8. Settings rows per approved D0.9.
9. Demolition per approved D0.2/D0.3.
10. Ride-along snags #1, #9, #10.
11. New/changed nudge + notification template rows in the seeder
    (wording can be rough — it's data; flag rows wanting Charlie's
    eyes).

## Scope — explicitly untouched

- escalation_exhausted — Phase 4 (exhausted-intent logging stays
  intent-only).
- Admin UI — Phase 5.
- Short case references (snag #4a) — separate pre-flip mini-batch.
- Landlord lookup on create form (#7), delivery webhooks (#8).
- Attachments on tenant replies — later.
- The landlord-side live path from 2b — no behaviour changes beyond
  the D9 header block in its mailables.

## Tests

- Disposition per the approved D0.1 list; no weakened assertions;
  deltas enumerated in the implementation report.
- New coverage, minimum: reply allowed/forbidden per the full D8
  state table; reply transitions + clock restart + freeze + fresh
  token; nudge fires live (mail queued, NO case_messages row —
  evidential invariant); dormancy transition live with audit event;
  hold pause respects max_days; hold expiry resumes with ball to
  landlord; revival inside window works, outside window gated;
  magic link signs in once, rejects reuse and expiry; create-case
  preview gates the first send; description block present on every
  outbound mailable; pretend mode executes nothing on either side;
  double-fire guards per approved D0.12; demolished paths gone.

## Acceptance

1. Suite green; count against the post-disposition baseline.
2. Live fire on gafol, jointly reviewed: full tenant reply round
   trip (reply from dashboard → landlord inbox → landlord email
   reply → webhook → dashboard); nudge fired by real sweep on an
   aged tenant-side clock; dormancy transition by real sweep;
   hold pause + expiry resume; magic-link arrival from a real
   notification email; create-case preview flow; D9 header block
   verified in every received email. Second sweep same day: no
   double-sends.
3. Pretend sweep: nothing executed, all intents logged.
4. Diff confirms demolition complete and untouched-list respected.

## Out of scope

Phase 4 (escalation_exhausted), Phase 5 (admin UI), snags #2/#4a/#7/#8,
attachments, per-category timescales, anything touching the DNS flip.

# Silence Model — Phase 2b close-out

**Written:** Sunday 2026-06-07, 06:02 local.
**Status:** CLOSED. Merged to main, deployed to gafol preprod, joint
live-fire review passed.

This is an immutable session write-up. Phase 2b's living artefacts
(brief, runbook, design doc, snagging list) keep their stable names
in `docs/`; this file is the dated record of the close-out itself.

---

## Merge

- **Branch:** `silence-phase-2b` (off `main` at `d21a15f`).
- **Pre-merge tag on main:** `pre-silence-phase-2b` → `d21a15f`.
- **Merge commit on main:** `6a608c9` — *Merge silence-phase-2b into
  main — landlord-side silence cutover.* `--no-ff`, pushed to origin.
- **Origin branch:** deleted post-deploy (gafol Plesk panel switched
  to main and redeployed first).
- **Diff stat:** 44 files, +1531 / −675. Adds the silence-2b
  implementation (sweep cutover, AutoEscalationTenantNotice mailable,
  dev:age-clock, three migrations) and brings in five docs from the
  branch (CLAUDE.md, pre-flip checklist, gafol runbook, snag list,
  this write-up's predecessor entries).

The merge included one commit that arguably belonged on main —
`6bdfa95 docs: pre-flip checklist for renters.rent production cutover`
— but it had landed on the branch first, so the `--no-ff` carried it
across. No harm done; recorded here so future-me knows main's history
between `pre-silence-model` and `pre-silence-phase-2b` was a single
docs commit (`d21a15f`), not two.

## Acceptance criteria — disposition

Brief (`docs/cc-brief-silence-phase-2b.md`) lists four:

1. **Suite green** — green at merge time, count reported in the
   implementation report against the post-disposition baseline.
2. **Live fire on gafol, jointly reviewed** — PASS (this doc covers
   the joint review).
3. **Pretend sweep at +35d sends nothing, intents still logged** —
   PASS (see §Live-fire results).
4. **Diff confirms preserved + demolished items** — PASS (the merge
   diff above lines up against the approved D0.2/D0.3 enumeration:
   SweepEscalations command + schedule + mailable + view gone, four
   stage Blade views gone, `next_stage_eligible_at` column + reads
   gone, awaiting_landlord → tenant_action_required transition gone,
   `template_key` dual-write + column gone; SweepDormancy /
   SweepHolds / `CaseController::sendNext` (D7) untouched).

## Live-fire results — gafol, 2026-06-07 ~04:17 local

Run per `docs/gafol-live-fire-runbook-2b.md`. Operator drove the
sequence in the Plesk Laravel Toolkit; outputs and Mailgun/Gmail
observations pasted into the joint-review chat.

**Database + sweep:**

- Sweep 1: `send_escalation=1`, `executed=1`. Case 2 produced
  `case_messages id=14`, outbound, `stage_at_send=2`,
  `letter_template_id=1` (D1 fallback to generic confirmed),
  `notice_number=2` in the rendered body.
- Sweep 2 (~5s later): `executed=0`. Case 2 verdict `no_action`,
  reasoning *"clock not expired (0/14)"* — lockForUpdate race guard
  fired against the freshly-restarted clock. **No double-send.**
- Case 2 post-sweep: `status=awaiting_landlord` (no transition, per
  D0.4), `current_stage=2`, `silence_clock_started_at` restarted at
  sweep-1 timestamp, exactly **one** `auto_escalation_sent` event.

**Mail (Mailgun + Gmail):**

- Stage-2 landlord notice (`CaseNotice`): Accepted, Delivered.
- Tenant notification (`AutoEscalationTenantNotice`): Accepted,
  Delivered. Active-row idiom honoured (template row present →
  notification fired).
- Both Gmail inboxes received the mail. Landed in spam under sandbox
  `DMARC:Quarantine` — sandbox-domain artefact, not a 2b finding;
  production (`mg.renters.rent`, DMARC-aligned) is unaffected.
- Tenant notification body rendered cleanly on first fire, all
  placeholders resolved.

**Pretend sweep at `--pretend-today=2026-07-11` (~+34d horizon):**

- 0 mail queued. All verdicts logged as shadow rows with
  `executed=false`, `is_pretend=true`. Tenant-side verdicts logged
  as `send_nudge` / `transition_dormant_intent`, landlord-side as
  `send_escalation`. **Pretend forces full shadow on both sides.**

All five joint-review checks from the runbook satisfied.

## Two diagnostic questions raised — resolutions

### Q1 — pretend-sweep arithmetic (CLOSED)

The shadow report showed **24 rows across three pretend dates**
(06-21, 07-01, 07-11) from what was supposedly one pretend sweep,
with 07-11 evaluated twice and the two evaluations disagreeing
(case 2: counter=2 vs counter=1; case 4: transition_dormant_intent
vs send_escalation). Concern: did the sweep silently start
multi-date stepping?

Code reading (`SilenceSweep::handle`, `SilenceClock::evaluate`):
**no.** Sweep resolves `$now` exactly once per invocation, iterates
non-terminal cases, writes one shadow row per case per invocation.
Six non-terminal cases per pretend run, full stop.

Actual mechanism (operator-confirmed via console log + `created_at`
grouping):

- `dev:reset` does NOT truncate `silence_shadow_log`.
- Yesterday's gafol session (2026-06-06 15:50–15:53) had left
  18 shadow rows from one real sweep plus three exploratory pretend
  probes at 06-21, 07-01, and 07-11.
- Today's `dev:lifecycle` reseeded `cases` 1..8 with fresh slugs.
  The stale shadow rows aliased onto today's reused case IDs, and
  the shadow report rendered them under today's slugs — making it
  look like one pretend sweep had walked multiple dates.
- The "07-11 evaluated twice with disagreeing counters" is the same
  effect: yesterday's 07-11 pretend (before sweep #3) recorded
  counter=1; today's 07-11 pretend (after sweep #3) recorded
  counter=2. Both rows were correct; the alias to today's case 2 made
  them look contradictory.

The cosmetic summary-line double-count is a real but minor bug —
`SilenceSweep`'s re-tally filters on `swept_at = $now AND is_pretend`,
and pretend mode uses a deterministic 09:00 `swept_at` for the
pretend date, so two pretend sweeps on the same date share a
`swept_at` and the second one's summary counts both runs' rows.
Underlying data is correct in every row.

Both effects are now snagged (#9 and #10), not blocking.

### Q2 — blank `issue_description` in the live-fired stage-2 letter (CLOSED)

The stage-2 letter rendered *"My description of the issue:"* followed
by an empty value. Notice-1 for the same case had filler text
(*"Please arrange to inspect and repair the reported issue."*).
Concern: 2b regression in the sweep's render path?

Trace:

- `cases` table has no description column. Tenant statement lives
  per-message on `case_messages.tenant_statement`.
- `SendCaseNotice::execute($case, ?string $tenantStatement = null,
  ...)` puts `$tenantStatement` into `issue_description`. Both
  subsequent callers pass null:
  - `CaseController::sendNext` (tenant click escalation).
  - `SilenceSweep::handleCase` (sweep auto-escalation).
- Only first-send (`CaseController::createCase`) passes the tenant's
  typed description. Stage 2+ has rendered blank since the click path
  shipped — pre-existing UX/evidential issue, NOT a 2b regression.
- Notice-1's "filler" came from `dev:letter`'s hardcoded default for
  `--statement`, not from any DB column; that's why notice-1 looked
  populated and notice-2 looked empty in the same lifecycle run.

Design doc lists `{{issue_description}}` as a whitelist placeholder
but does not specify a source for escalation rounds. Decision
deferred to Phase 3 alongside tenant reply UI, where the rendering
pipeline is naturally revisited.

Snag #11 records the three implementation options (carry forward
prior statement, persist on case, template surgery) — Phase 3 to
decide.

## Snags raised at joint review (committed b7bc81c)

- **#9 — `dev:reset` truncation list missing `silence_shadow_log`.**
  Stale rows alias reseeded case IDs and corrupt shadow-report
  storytelling. One-liner fix: add `'silence_shadow_log'` to
  `DevReset::TABLES`. No migration.
- **#10 — `silence:sweep` summary line double-counts under shared
  pretend `swept_at`.** Cosmetic. Fix: tally from row IDs written
  this invocation rather than re-querying by `swept_at`.
- **#11 — stage 2+ letters render blank `issue_description`** on both
  click and sweep paths. Pre-existing, not a 2b regression. Three
  options recorded; Phase 3 decides at the same time it wires tenant
  reply.

All three deferred per joint-review rulings.

## Open thread for Phase 3

Phase 3 is the **tenant-side go-live**. From the brief and the design
doc, the scope it inherits:

- **Tenant reply UI.** State-machine change, not just a button. Per
  snag #4 in the snagging list (the design-required entry), open
  questions:
  - Which states allow tenant reply? (`awaiting_tenant_review` =
    yes; `awaiting_landlord` = maybe / add-info; `on_hold` /
    `resolved` / `dormant` = ?).
  - What does tenant reply transition to? (likely
    `awaiting_tenant_review` → `awaiting_landlord` — ball back to
    landlord).
  - Reuses outbound-letter + token machinery? (each tenant reply is
    effectively another letter in the thread).
  - Token continuity over a long thread vs the 90-day expiry model.
- **Tenant-side silence model goes live.** `silence:sweep` for
  tenant-side verdicts (`send_nudge`, `transition_dormant_intent`)
  flips from shadow to executing. `SweepDormancy` and `SweepHolds`
  die at this cutover (preserved through Phase 2b for this exact
  handoff).
- **D7 resolution.** `CaseController::sendNext` (tenant click
  escalation) — preserved through 2b as the landlord-replied-but-
  refused path. Phase 3 to decide whether it stays, gets folded into
  the new tenant reply UI, or is replaced.
- **Snags to fold in:**
  - **#11 (blank issue_description)** — natural fit; rendering
    pipeline is touched anyway. Decide source of truth across both
    click and sweep paths.
  - **#9 (`silence_shadow_log` in `dev:reset`)** — small and orthogonal;
    fold in if the snag batch runs before Phase 3 starts.
  - **#10 (summary-line double-count)** — same; cheap, optional.

Phase 4 (escalation_exhausted), Phase 5 (admin UI), and snag #8
(delivery webhooks) remain explicitly out of Phase 3 scope.

## What we're NOT doing right now

Production cutover. `renters.rent` is governed by
`docs/pre-flip-checklist.md`, A1 of which is "silence model green
through Phase 2b on gafol." That gate is now clear, but the
remaining A-list items (mailgun production env vars, schedule:run
cron, prod data audit) are independent and will be worked through
on their own session.

---

## Session bookkeeping

- Suite state at merge: green (operator-reported; implementation
  report has the count delta against the 448 baseline).
- Repo refs after this session:
  - `main = 6a608c9`
  - `pre-silence-phase-2b` tag → `d21a15f`
  - `pre-silence-model` tag → older Phase 1 boundary
  - Local: on `main`, working tree clean (this write-up will commit
    it).
  - `silence-phase-2b` branch: deleted locally + on origin.
- Next session: Phase 3 design OR the snag batch — decided at start.

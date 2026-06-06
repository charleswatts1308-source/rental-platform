# LLCS Silence Model — Design

**File:** `docs/llcs-silence-model-design.md`
**Status:** Design agreed. Supersedes the fixed-ladder escalation model.
**Origin:** Half-duplex snag (tenant could view landlord replies but not respond). Design sessions Fri/Sat 2026-06-05/06.

---

## 1. The model in one paragraph

The conversation is the case; escalation is a silence detector. Tenant and
landlord correspond freely once a case is open. A single clock measures
silence since the latest message from the *other* party. When the clock
expires, the system wakes whichever party owes a response: landlord silence
fires the next escalation letter (formal, evidential, ratcheting); tenant
silence fires a private nudge (supportive, non-evidential, sliding toward
dormancy). When the escalation ladder is exhausted against continued
landlord silence, the case reaches `escalation_exhausted` and the platform's
job becomes signposting external remedies and handing over the evidence
bundle.

This replaces the old model in which the four-stage ladder was the spine of
the case and tenant progression was the only tenant action.

---

## 2. Decisions (D1–D6, all agreed)

### D1 — A "stage" is a severity level, not a ladder rung

The four escalation letter contents survive unchanged. Only the trigger
changes: from "tenant progresses the case" to "landlord silence threshold
hit". Letters move out of code into a `letter_templates` table so wording
and legal references can change without a code release.

- Placeholder rendering over a fixed whitelist of variables
  (`{{tenant_name}}`, `{{case_reference}}`, `{{issue_description}}`,
  `{{deadline_date}}`, `{{response_days}}`, `{{notice_number}}`, …).
  **Not Blade** — Blade can execute PHP; a compromised admin account must
  not become RCE.
- **Fallback lookup rule** for escalation sends at counter N: use the
  active `escalation` template with `stage = N` if one exists; otherwise
  fall back to the active `stage = NULL` generic wake-up (rendered with
  `{{notice_number}}`). Content choice — one generic letter vs graduated
  per-stage letters — is therefore made entirely by which rows exist, and
  can change any time without code. **v1 seeds the generic wake-up only**
  (one landlord, one tenant nudge), not four differentiated letters; the
  D5 landlord closer serves as the heavyweight final letter.
- Rendered letter bodies are frozen in `case_messages` at send time —
  evidence is what was sent, never re-rendered. Each sent message also
  stamps the template row id + its `updated_at`, answering "which wording
  was in force".
- Template editing path for v1: phpMyAdmin. No admin CRUD screen yet.
- Seeder ships generic wake-ups (landlord + tenant nudge) and the D5
  notification templates. The original four-letter ladder content is
  retired; per-stage letters can be reintroduced later as `stage = N`
  rows if graduated formality proves worth having.

### D2 — One clock, turn-based; two species of silence

The clock always measures time since the latest message, and its
consequence depends on whose turn it is:

| Whose silence | Consequence | Character |
|---|---|---|
| Landlord (ball in their court) | Next escalation letter fires | Formal, evidential, part of the correspondence record |
| Tenant (ball in theirs) | Nudge email to tenant | Private, supportive, **never** in the landlord-facing thread or exported evidence record |

Tenant nudge ladder: nudge → nudge → "case will be marked dormant" →
dormant. Dormancy becomes the end of an explained, recoverable sequence,
not a silent timeout. A tenant reply at any point resumes the case and
flips the ball back to the landlord. The existing `on_hold` state serves
as an explicit tenant "pause this case" action (suspends nudges for a
stated period) — may be wired in v1 or deferred.

Nudge copy lives in the same `letter_templates` table, distinguished by a
`type` column.

### D3 — The escalation counter is a ratchet

Increments only, never resets, derived from the count of escalation
letters already sent on the case. Landlord goes silent → stage 2 fires →
landlord re-engages → goes silent again → next silence fires **stage 3**.

Rationale: the system cannot judge reply quality, so a reply must not buy
a reset (a landlord could reply once per cycle and hold the case at low
temperature forever). The accumulated record of unreliability is itself
evidence — and in the absent-landlord scenario (agent collecting rent for
an untraceable landlord), a case showing stages 1→4 with zero replies *is*
the diagnosis.

### D4 — Intervals are configurable data

A `settings` table (key, value, timestamps), seeded with defaults, read at
runtime. Initial keys:

- `escalation.interval_days` = 14 (flat across stages for v1)
- `escalation.max_notices` = 4 (after which → `escalation_exhausted`, D5)
- `nudge.first_days` = 10
- `nudge.second_days` = 20
- `nudge.dormancy_days` = 30

Two guardrails:

1. **In-flight semantics.** A settings change applies only to clocks
   started after the change. Deadlines are computed from the value in
   force at clock start (store the deadline, or the interval used, on the
   case). Changing 14→7 must never retro-fire letters at cases already
   past the new threshold.
2. **Letter/deadline consistency.** Stage letters render
   `{{response_days}}` from the same setting the scheduler enforces. A
   letter promising 14 days while the scheduler enforces 7 is evidentially
   embarrassing.

Not a settings framework. Four rows, phpMyAdmin editing, done.

### D5 — `escalation_exhausted`: what happens when the ladder runs out

Stage 4 fires; landlord silence develops again; clock expires; there is no
stage 5. The case transitions to a new state, `escalation_exhausted`.

Machinery (code):

- The state exists. The clock stops **permanently** — no further automatic
  letters, ever, for this case.
- Tenant is notified by email at the transition ("the escalation process
  has run its course — log in to see your options").
- A one-shot closing letter to the landlord **send-point exists** at the
  transition. Whether it fires is data: *if an active `letter_templates`
  row of type `exhaustion_landlord` exists, render and send; else skip
  silently.* The template row is the switch.
- Transitions out: landlord reply (late arrival via webhook) revives the
  case to active correspondence; tenant can still close
  (resolved/abandoned). Nothing else moves it.
- Branch signal for guidance: "zero landlord messages ever received" —
  the condition is code; each branch's content is data.

Content (data, deferred permanently — edit rows, not code):

- Signpost guidance shown on the case page at this state: ombudsman,
  council environmental health, court route, evidence bundle.
- Absent-landlord branch: identify-the-landlord tools, s.1 LTA 1985
  written demand via the agent, council enforcement.
- The platform **does not act externally** on the tenant's behalf — no
  auto-filing. Signpost state only: here is your evidence, here is the
  door.

### D6 — Tenant follow-up restarts the clock

The clock is always *time since the latest tenant message*. A tenant
follow-up on day 10 of the landlord's 14 restarts the 14: the landlord has
new material to respond to, and a tenant who wants the clock to run simply
stays quiet. One rule, no special cases.

### D7 — Tenant-initiated escalation on unsatisfactory reply (OPEN)

Identified at 2b: silence detection never escalates against a landlord
who replies but refuses ("won't fix it") — an engaged-but-unhelpful
landlord stops the clock every cycle and the ladder never climbs. The
tenant needs a path to escalate on reply *content*, which the machine
cannot judge.

Interim answer (2b): the existing tenant "send next notice" action
(CaseController::sendNextNotice) survives the 2b demolition and serves
as tenant-initiated escalation. It is consistent with the model — the
escalation letter is a tenant message; ball flips to landlord, clock
restarts, ratchet advances.

To resolve at Phase 3, alongside the tenant reply action: whether
tenant-initiated escalation remains a distinct action, merges into the
reply flow ("reply" vs "reply and escalate"), or is redesigned. Until
then D7 is open and the click stays.

---

## 3. The razor (cross-cutting principle)

**If it's words a tenant or landlord reads, it's a row. If it's what the
machine does, it's code.**

Corollary — the optional-communication idiom: anywhere the machine has an
optional send, the pattern is *"active template row of type X exists →
render and send; else skip."* The template table is the on/off switch.
States, transitions, triggers, and send-points stay in code; every if-and-
what of messaging is data. Do not generalise into a soft-coded workflow
engine — there is one workflow, and phpMyAdmin is the config UI.

---

## 4. Schema changes

| Change | Notes |
|---|---|
| New table `letter_templates` | id, code, description, subject, body, `type` (escalation / tenant_nudge / exhaustion_landlord / tenant_notification …), `stage` (nullable — NULL = generic fallback, per D1 lookup rule), `active`, timestamps. Seeded with generic wake-ups (one landlord, one tenant nudge) + D5 notifications. |
| New table `settings` | key, value, timestamps. Seeded per D4. |
| `case_messages` gains template reference | `letter_template_id` (nullable — inbound messages and free-text tenant replies have none) + snapshot of template `updated_at`. Rendered body already stored (**assumption — verify, see §7**). |
| `cases` gains clock fields | e.g. `clock_deadline_at` (or `last_tenant_message_at` + `interval_days_in_force`), and possibly `last_actor`. Exact shape is CC's call within the D4 guardrails. |

No new "cases-progress" table: correspondence history and progress history
are the same history. The escalation counter is derived from
`case_messages` rows where template type = escalation.

---

## 5. State machine implications

The existing 21-transition machine is refactored, not extended. The states
plausibly collapse around an *active correspondence* condition (ball
position derivable from last message direction), with these fixed points:

- New state: `escalation_exhausted` (per D5).
- Dormancy reached only via the explained nudge sequence (per D2).
- `on_hold` repurposed as explicit tenant pause.
- Tenant gains a reply/add-information action during active
  correspondence — the original half-duplex snag. Each tenant reply
  reuses the outbound letter machinery: same Mailgun send path, same
  `{token}@mg.renters.rent` Reply-To, fresh token per send (sidesteps the
  90-day expiry question), logged to `case_messages`.

Many existing tests will break **correctly** — they assert the old model.
They are the demolition survey: each break identifies a behaviour change;
rewrite assertions to the new model.

## 6. Scheduler

The sweep job changes from "stage N deadline passed → fire stage N+1" to:

1. Find cases with an expired clock.
2. Determine whose silence (ball position).
3. Landlord → fire next escalation letter (ratchet counter + 1); if
   counter already at max, transition to `escalation_exhausted` (D5 flow).
4. Tenant → fire next nudge; if nudge ladder exhausted, transition to
   dormant.
5. Reset/stop clocks per the transition.

---

## 7. Pending verifications (before content is written / brief is sent)

1. **Verify `case_messages` stores the full rendered body** of outbound
   letters. If anything less, fixing that is part of Phase 1 — evidence
   must be frozen at send time.
2. **Verify s.1 LTA 1985 current position** (landlord identity disclosure,
   21-day criminal offence) against the Renters' Rights Act 2025 before
   the absent-landlord guidance content is written. The detail in this doc
   is from training data, unverified.

---

## 8. Phased CC brief outline

1. **Phase 1 — Schema + templates.** `letter_templates`, `settings`,
   `case_messages` template-ref column, seeders, placeholder renderer
   (whitelist), §7.1 verification/fix. No behaviour change yet — existing
   sends switch to rendering from the table.
2. **Phase 2a — Clock alongside ladder (shadow mode).** Introduce clock
   fields, turn-detection, and the new scheduler logic running in
   parallel with the old ladder — new model **logs its intended actions
   only**, sends nothing, transitions nothing. Old behaviour fully
   intact; 377-test baseline still green. Exploratory check: compare
   shadow log against expected behaviour on demo cases.
3. **Phase 2b — Landlord-side cutover + demolition.** Landlord silence
   fires escalations live via the sweep (tenant notified per
   auto-escalation, active-row idiom); counter ≥ max logs exhausted
   intent only (no state until Phase 4). Tenant-side verdicts (nudges,
   dormancy) REMAIN SHADOW — a live nudge points at a tenant action
   that doesn't exist until Phase 3. SweepEscalations + EscalationEligible
   + ladder timing demolished; SweepDormancy/SweepHolds and the tenant
   "send next notice" click (D7 interim) survive until Phase 3.
   `--pretend-today` always forces full shadow. Test-suite refactor
   lands here, report-first: disposition per broken test, weakened
   assertions not acceptable.
4. **Phase 3 — Tenant reply + tenant-side go-live.** UI action +
   controller + state handling; reuses outbound letter machinery; the
   original snag closes. Tenant-side silence handling (nudges,
   dormancy sequence) goes LIVE here — the nudge finally has an action
   to point at. SweepDormancy/SweepHolds demolished; D7 resolved
   (tenant-initiated escalation: distinct action, merged into reply
   flow, or redesigned). Nudge sends are mail-only, never
   case_messages rows (evidential-record invariant).
5. **Phase 4 — `escalation_exhausted`.** State, transitions, tenant
   notification, landlord-closer send-point, guidance content scaffold
   (content rows can be rough; they're data).
6. **Phase 5 — Admin UI for templates + settings.** Gated on Phases 1–4
   green and exploratory-verified. Two CRUD screens behind `is_admin`:
   templates (list / edit / activate-deactivate, plain textarea body, no
   WYSIWYG) and settings (edit values only — no create/delete; keys are
   machinery vocabulary). Plus preview (render against sample data via
   the Phase 1 renderer — catches misspelled placeholders) and save
   validation (non-empty body, whitelist warning). phpMyAdmin remains
   the editing path until this lands.

Each phase independently deployable to gafol.rent / dotrent.net.

---

## 9. Out of scope

- Admin CRUD UI for templates/settings — deferred to Phase 5 (gated on
  Phases 1–4 verified); phpMyAdmin until then.
- Per-repair-category timescales (Awaab's Law urgency tiers) — future,
  category-driven, not stage-driven.
- Tightening per-stage intervals — revisit if real cases show gaming.
- Soft-coded workflow engine — explicitly rejected.
- Attachments on tenant replies — later.
- External auto-filing (ombudsman/council) — never; signpost only.

---

## 10. Sequencing

This work happens **before** the DNS flip. Zero production data makes it
the cheapest it will ever be; the flip carries the right model from day
one. PWA work remains deferred behind both.

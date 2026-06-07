# LLCS Silence Model — Design

**File:** `docs/llcs-silence-model-design.md`
**Status:** Design agreed. Supersedes the fixed-ladder escalation model.
**Origin:** Half-duplex snag (tenant could view landlord replies but not respond). Design sessions Fri/Sat 2026-06-05/06.

---

## 1. The model in one paragraph

Both parties correspond freely. Silence — and only silence — drives the
machinery: landlord silence fires escalation letters (formal, evidential,
ratcheting); tenant silence fires private nudges sliding toward dormancy.
Cases end by tenant decision (resolved / abandoned), by the dormancy
timer, or by the escalation ladder running out
(`escalation_exhausted`), at which point the platform's job becomes
signposting external remedies and handing over the evidence bundle.
The tenant has three actions: reply, resolve, abandon (plus an explicit
pause). Everything else is the clock. The platform never judges message
content — not the landlord's, not the tenant's.

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

### D7 — RESOLVED: escalation is silence-only; no tenant-initiated escalation

A landlord who replies — even unhelpfully ("not my problem") — has
engaged; silence detection correctly does not fire. A dispute about the
*substance* of a reply is not something the platform adjudicates or
pressure-escalates: the platform's job there is the record plus
signposting (guidance/FAQ content: s.11 rights, what counts as
disrepair, council / ombudsman routes — all data rows, never code).

Consequences:
- `CaseController::sendNextNotice` and its UI are demolished in Phase 3.
  The escalation ladder is driven exclusively by `silence:sweep`.
- The hard case is covered without any button: landlord replies "I'll
  fix it next week", then nothing — the reply restarted the clock,
  silence resumes, the sweep fires the next notice 14 days after the
  reply. The tenant need only stay quiet (D6).
- Rationale matches D3's own logic: the system cannot judge reply
  quality, so it must not offer the tenant a button whose meaning is
  "I judged this reply inadequate".

### D8 — Tenant reply: availability and transition

The tenant gains a reply / add-information action. Availability by
state:

| State | Reply? | Notes |
|---|---|---|
| awaiting_tenant_review | Yes | The original half-duplex snag; the core of Phase 3 |
| awaiting_landlord | Yes | Add-info. UI hint: "sending this restarts your landlord's response time" (D6) |
| on_hold | Yes | Reply IS the resume action |
| dormant | Yes, within `dormancy.revival_days` | Beyond the window the page offers "raise a new case" instead (D11) |
| resolved / abandoned | Never | Deliberate endings stay ended; recurrence = new case, which may reference the old by quoting its reference |
| escalation_exhausted | Deferred to Phase 4 | Expected: message-on-record, clock stays permanently stopped (D5) |

Every tenant reply transitions the case to (or keeps it in)
`awaiting_landlord`: ball to landlord, clock restarts (D6). Replies
reuse the outbound letter machinery — same Mailgun path, fresh token
per send, frozen verbatim in `case_messages`.

Rule of thumb, for the record: a tenant message wakes anything the
tenant paused or neglected; it never reopens what was deliberately
ended.

### D9 — Case description: fixed at creation, on every outbound mail

`cases.description` — the tenant's original framing of the issue, set
at case creation, immutable thereafter. Every system-rendered outbound
email carries a standing header block: property address + case
reference + original description. This applies to escalation letters,
tenant replies (the block *frames* the tenant's verbatim words, never
alters them), tenant nudges, and tenant notifications.

Rationale: a landlord or agent with twenty tenants must never need
archaeology to know which property and which problem; every letter in
the evidence bundle becomes self-contained.

Closes snags #11 (blank description on stage 2+, both paths) and #3
(dev seed descriptions — `dev:lifecycle` SPECS gains a description
column; the filler default dies).

### D10 — on_hold: explicit tenant pause, with guardrails

Wired in Phase 3. Pause-until-date form; existing hold-expiry sweep
resumes the case, ball with landlord.

Guardrails (landlord-abuse-via-tenant-pause considered and defanged):
- New settings row `hold.max_days` (default 60) caps the pause.
- The ratchet (D3) means a hold never resets escalation position — the
  landlord buys quiet weeks, never a restart.
- Button copy (template/content, not code): pausing stops reminder
  letters; if the landlord promised a fix, pause until just after the
  promised date.

Tenant *neglect* (no hold, just disengagement) is accepted as outside
the tool's power: the nudge ladder makes it loud, recoverable, and
non-destructive of position; it cannot prevent it. Same neutrality
that ruled D7.

### D11 — Dormancy revival window

New settings row `dormancy.revival_days` (default 90). A dormant case
revives via tenant reply within the window. Beyond it the case page
withdraws the reply action and offers "raise a new case (reference the
old one in your description)" — guidance, not a locked door; the value
is soft and editable.

Rationale: one case = one repair issue = one clean evidential record.
The endless-support-ticket thread that drifts across months and topics
is useless as an evidence bundle. No `related_case_id` machinery yet —
quoting the old reference in the new description suffices; revisit if
cross-case pattern-spotting earns it.

### D12 — Magic-link sign-in for all tenant email arrivals

Every Phase 3-touched outbound email links to the case via a signed,
single-use, short-expiry login token: clicking signs the tenant in and
lands them on the case page. No password wall between a notification
and the case it announces.

Threat model: inbox access already equals account takeover via
password reset, so the link grants nothing new; the platform holds
repair correspondence, not high-confidentiality data. Tenant privacy
posture (landlord never sees tenant contact details) is unaffected —
links travel only to the tenant's own inbox.

Mechanics: token table, signed route + middleware, single-use,
expiry. Supersedes snag #5 (login email pre-fill — pointless once
links log you in) and closes snag #6.

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
| `cases.description` | Tenant's original framing, set at creation, immutable (D9) |
| New table for magic-link tokens | Single-use, expiring, per-tenant (D12) — shape is CC's call |
| `settings` rows | `dormancy.revival_days` = 90 (D11), `hold.max_days` = 60 (D10) |

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
4. **Phase 3 — Tenant reply + tenant-side go-live.** Reply UI +
   controller per D8; reuses outbound letter machinery; the original
   snag closes. Tenant-side silence handling (nudges, dormancy
   sequence) goes LIVE — nudges finally have an action to point at.
   on_hold wired as explicit pause per D10. `cases.description` per
   D9 across all outbound mail. Magic-link sign-in per D12 on all
   touched emails. Dormancy revival window per D11. DEMOLITION:
   SweepDormancy, SweepHolds (absorbed by silence:sweep),
   CaseController::sendNextNotice + UI (D7 resolved). Nudge sends
   remain mail-only, never case_messages rows (evidential invariant).
   Ride-along snags: #1 (nav title), #9 (shadow-log truncation), #10
   (sweep summary tally).
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

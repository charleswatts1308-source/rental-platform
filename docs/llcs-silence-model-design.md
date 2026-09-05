# LLCS Silence Model — Design

**File:** `docs/llcs-silence-model-design.md`
**Status:** Design agreed. Supersedes the fixed-ladder escalation model.
**Origin:** Half-duplex snag (tenant could view landlord replies but not respond). Design sessions Fri/Sat 2026-06-05/06.

---

## 0. What gives the record its power

A record of the conversation between tenant and landlord is the backbone
of this system. The power is in the FACT of the record and in the
landlord knowing it exists. Most cases will be resolved, abandoned or
quietly settled long before anyone examines the detail; only a tiny
minority will ever have their contents tested for accuracy in a court.

That is a sorting rule, not an excuse. It says:

**Invest in the record's completeness, continuity and credibility.
Do not over-invest in the forensic fidelity of individual artefacts.**

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

## 2. Decisions (D1–D13, all agreed; D15 added post-Phase-3; D14 = the
Phase-4 build of D5 — see the D5 implementation note; D16 = Phase-5
Admin/Config UI, designed post-D15)

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
stated period) — wired in Phase 3, see D10.

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

**Implementation note — Phase 4 / D14 (built 2026-06-14).** D5 is the
authoritative spec; the Phase-4 build ("D14") implements it and pins the
details D5 left open. Nothing below contradicts D5 — it extends it.

- **Reachable only by the never-engaged path.** D15 made escalation
  tenant-gated for *engaged* landlords (withheld → authorise-nudge →
  dormant), so an engaged case never climbs to `max_notices`. Exhaustion
  is therefore a **never-engaged terminal** only. The verdict's
  `counter >= max_notices` check sits above the D15 engagement branch in
  `SilenceClock`; that ordering is benign because an engaged case cannot
  reach `counter >= max`, and a guard test locks the invariant.
- **The landlord closer fires (D5's send-point, made real).** On the
  transition, `SendExhaustionCloser` renders + sends the active
  `exhaustion_landlord` row (`exhaustion_landlord_closer`) with
  `stage_at_send = NULL` — a real, frozen `case_messages` row that does
  **not** count toward the ladder (D3 predicate excludes NULL-stage). It is
  **one-shot**: skipped if a closer already exists on the case, so a
  tenant-web revival that later re-exhausts does not re-fire it. This is
  the binding reading of "the clock stops permanently — no further
  automatic letters, ever."
- **"Clock stops permanently" reconciled with allow-reply.** A reply
  restarts the clock, but the D3 ratchet (counter never resets) keeps
  `counter >= max`, so the verdict can only ever re-exhaust — never emit
  another escalation letter. Letters stop; the timestamp may move.
- **Allow-reply revives on both edges (mirroring dormancy's split).** A
  **tenant** web reply → `awaiting_landlord` (`tenant_replied`); a
  **landlord** email reply → `awaiting_tenant_review` (`inbound_received`,
  via `HandleInboundReply`). The landlord edge is mandatory — without it
  the webhook would record the inbound but strand the case sweep-inert with
  the ball wrongly on the tenant. **No revival window** (unlike dormancy):
  a reply revives whenever it lands.
- **Engagement-flag reconciliation with D15.** A landlord email revival
  flips `landlord_engaged` true (a genuine landlord inbound, same rule as
  everywhere), so the revived case is thereafter D15-gated rather than
  auto-escalating. A tenant reply does not touch the flag.
- **Tenant stance (cosmetic).** `cases.exhausted_stance`
  (null | abandoned | unresolved) is a displayed label only — read by the
  UI and by **nothing** in the sweep/verdict/clock/state-machine. The
  tenant is never forced to choose. The tenant may still deliberately close
  the case (resolved / abandoned).
- **Signposting.** A members-wall page (`members.escalation-routes`, auth,
  not in public nav), stubbed; real wording solicitor-deferred. Reached
  from the exhausted case page and the tenant exhaustion notice. The closer
  + tenant notice + signposting wording are **solicitor-gated for
  production**.

### D6 — Tenant follow-up restarts the clock

The clock is always *time since the latest tenant message*. A tenant
follow-up on day 10 of the landlord's 14 restarts the 14: the landlord has
new material to respond to, and a tenant who wants the clock to run simply
stays quiet. One rule, no special cases.

### D7 — RESOLVED: escalation is silence-only; no tenant-initiated escalation

> **Partially superseded by D15** (engagement-gated escalation). D7's
> ban on a free-standing tenant escalation trigger still holds; D15
> introduces a *gated authorisation* of a machine-prepared notice, and
> only for the engaged-then-quiet landlord class. Read D7 together with
> D15.

A landlord who replies — even unhelpfully ("not my problem") — has
engaged; silence detection correctly does not fire. A dispute about the
*substance* of a reply is not something the platform adjudicates or
pressure-escalates: the platform's job there is the record plus
signposting (guidance/FAQ content: s.11 rights, what counts as
disrepair, council / ombudsman routes — all data rows, never code).

Consequences:
- `CaseController::sendNext` and its UI are demolished in Phase 3.
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
| escalation_exhausted | Yes (D14, no window) | Allow-reply revives (D5/D14): tenant reply → awaiting_landlord; landlord email → awaiting_tenant_review. Clock restarts but the D3 ratchet bars any further escalation letter. |

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

### D13 — Letter consent: authorise once, preview the first, notify the rest

Letters go out in the tenant's name ("Yours faithfully, {{tenant_name}}").
The consent model:

1. **Authorisation is given once, at case creation.** Explicit wording at
   the create-case form: by opening this case the tenant authorises
   renters.rent to send escalating letters in their name if the landlord
   does not respond. The letter wording is viewable at that point (the
   Phase 1 renderer against the case's actual data).
2. **The first letter is previewed before it is sent.** Case creation is
   the one moment a preview costs nothing — the tenant is present and
   acting. The form flow becomes: enter details → see notice 1 rendered
   with their actual description → confirm → send. This also catches
   description typos before they are frozen into evidence (D9 makes the
   description immutable and ubiquitous, so this is the only correction
   point).
3. **Subsequent letters are notified after, never gated before.** The
   sweep sends; the tenant notification says what went out; the full
   letter sits on the case record. Pause (D10) is the standing opt-out
   for a tenant who wants letters to stop.

Explicitly rejected: per-letter tenant approval ("here's the next
letter, press send"). It would reintroduce the disease the silence
model cured — escalation reliable only up to the tenant's attention —
and recreate D7's judgment step in new clothes. The tenant controls
the process (open, pause, resolve, abandon) and sees everything; they
do not gate each send. The authorisation is the contract; the
templates are the published wording; the case record is complete.

Phase 3 build consequence: the create-case flow gains the preview +
confirm step (today it fires notice 1 immediately with no preview).
The authorisation copy is content (template/data), not code.

### D15 — Engagement-gated escalation (lands before Phase 4)

**Origin: a live-harm gap found after Phase 3.** A tenant reply is
uniform (D6/D8): every reply → `awaiting_landlord`, clock restarts. So
a "thanks, all sorted" reply from `awaiting_tenant_review` lands in
`awaiting_landlord`, restarts the landlord clock, and after the
escalation interval of (entirely expected) landlord silence the sweep
fires a real, evidential escalation letter at a landlord on a case the
tenant treats as closed. The tenant caused a wrongful escalation by an
ordinary, well-meant action. This harm exists **only** where the
landlord has engaged — a "thanks" presupposes something to thank them
for.

**Supersedes D7, partially and on new grounds.** D7 banned a
free-standing tenant escalation trigger ("send next notice"), and its
rationale stands: the platform cannot judge reply quality, so it must
not offer a button meaning "I judged this reply inadequate." D15's
authorise action is **not** that button. It is a **gated authorisation
of a notice the machine has already prepared**, surfaced only when the
machine itself determines an escalation is due (landlord silence past
the interval, counter below max) **and** the landlord has engaged.
The tenant does not author or judge anything; they consent to a send
the platform would otherwise have made automatically. The thank-you
harm post-dated D7's reasoning and is the new ground. D7 keeps
governing the never-engaged case (full automation) and the
substance-of-a-reply dispute (record + signpost, never pressure).

**The two-class model.** A new one-way fact governs whether escalation
auto-fires: *has this landlord ever replied on this case?*

| Class | Escalation behaviour | Tenant posture | Legitimacy |
|---|---|---|---|
| **Never-engaged** | FULLY AUTOMATIC, exactly as today | NOTIFIED on every auto-send (informational; D0.6 notify-on-send, already built — no action, no button, no `case_messages` row, no ball move, no clock) | The D13 create-case authorisation is standing consent to pursue a silent landlord |
| **Engaged-then-quiet** | TENANT-AUTHORISED — the sweep WITHHOLDS the send and surfaces the prepared notice for the tenant to authorise (reusing the D13 preview/authorise pattern) | ASKED — nudged to authorise; if they never do, the case falls to dormancy (below) | The platform does not chase harder than the tenant's will; the tenant had an opening to spend energy and declined to authorise |

Posture maps to who carries the energy: machine acts for you → it
**tells** you; your will should govern → it **asks** you. This also
closes D7's other open case — the engaged-but-*refusing* landlord is an
engaged landlord, so tenant-gated re-push is his home too.

**Ruling 1 — what counts as "engaged".** ANY token-resolved inbound
flips the flag, *including* a quarantined / from-address-mismatch
message. A generous definition fails SAFE (more cases become
tenant-gated, fewer auto-escalate — the platform under-pursues); a
stingy definition fails harmful (an engaged case wrongly reads
never-engaged and fires a letter that should have been gated). No
auto-responder / out-of-office / bounce detection at pilot. A spam
reply carrying a resolvable token wrongly marks engaged → switches off
auto-pursuit → the tenant need only authorise: the tolerable failure.
The flag flips on the same condition that already flips the
ball/clock in the inbound handler; it is idempotent and never resets.

**Ruling 2 — the held case stays landlord-ball.** An engaged-then-quiet
held case is *genuinely* landlord-ball: the last message is the
tenant's own outbound reply, so the message-direction rule correctly
reports the ball with the landlord. We do **not** force it to
tenant-ball — doing so would make the ball rule lie and corrupt the
message-direction invariant the whole model trusts. Consequently the
authorise-nudge ladder for the held case is **new landlord-ball logic**,
not a reuse of the tenant-ball nudge path; and the unauthorised tail
needs **one new `awaiting_landlord → dormant` transition edge**.
Dormant is the existing state and the **D11 revival window applies
unchanged** — only the edge into it is new. (Design originally
under-costed this as reuse; it is new tail logic, built cleanly as
such.)

**No new `CaseStatus`.** "Tenant authorisation required" is a derived
condition (`landlord_engaged` true + clock expired + counter < max + no
newer inbound), computed from the same silence verdict the sweep uses —
no stored state, no new sweep-exclusion wiring. The case sits in
`awaiting_landlord` throughout.

**Counter / evidential invariants untouched.** Engagement is independent
of the D3 ratchet (counter = escalation letters sent; engaged = inbounds
received). Authorisation fires the *existing* auto-escalation send path,
which ratchets the counter and freezes the letter in `case_messages` as
before. The held state writes no escalation row.

**Backfill caveat (record only; moot at pilot).** Default-false means an
un-backfilled *engaged* case would read never-engaged and auto-escalate
— the **harmful** direction (no worse than today's behaviour, but not
benign; the earlier "safe" framing was wrong). At pilot, `migrate:fresh`
from files means every case is created with the flag and flipped by the
inbound handler, so there is nothing to backfill. If live cases ever
existed, the flag is derivable:
`landlord_engaged = EXISTS(case_messages WHERE direction=inbound AND
sender_role=landlord)` (any inbound, per Ruling 1).

**Sequencing.** D15 lands **before** Phase 4 (`escalation_exhausted`),
which waits behind it. Out of scope and explicitly not folded in:
success-recording (the separate "satisfied case → dormant rather than
resolved" question), Phase 4, content/intent analysis of email text,
and snags #12–19.

### D16 — Admin / Config UI (Phase 5)

**Status:** designed and ruled (21 Jun 2026), ready for CC brief. Not yet built.

**Governing principle.** D16 is the operational payoff on the foundational razor — *words a tenant or landlord reads are rows; machine behaviour is code.* Phase 1 built the storage half (`letter_templates`, `settings`); editing those rows still requires a seeder change or a raw phpMyAdmin edit. Phase 5 gives the rows a proper editing surface so wording and interval changes never require a developer action.

A second line is drawn through the whole phase: **the admin edits the rules, never reaches into a case.** Changing a template or a setting alters machine behaviour going forward — a legitimate admin act. Reaching into a specific case's state or frozen record breaks the `case_events` evidential spine and is deliberately *not* given a UI (see Surface C).

#### Auth — closed (was a D0 assumption, now confirmed fact)

Admin authentication and route-level authorisation are already in place:

- Real `is_admin` boolean column (migration `2026_05_24_140028`), cast on the `User` model.
- `AdminMiddleware` does a null-safe check (`! Auth::user()?->is_admin` → `abort(403)`).
- The admin route group is stacked behind `auth + verified + admin` (`web.php:89`), registered as the `admin` alias in `bootstrap/app.php`.

So Phase 5 is **screens, not gatekeeping.** The three surfaces join the existing admin-gated route group — there is no gate to build, only routes to add behind the gate that exists. A logged-in non-admin typing an `/admin/*` URL already receives a 403; this was verified, not assumed.

**Out of scope for this doc (separate commit):** an admin-security hardening pass — remove `is_admin` from the `User` `$fillable` (latent mass-assignment / privilege-escalation vector; not currently exploited by any input path, but should not be fillable), set it explicitly in the dev/seed commands, add a regression test (non-admin → 403 on `/admin/*`, and `is_admin` survives a crafted profile-update POST), and correct the stale `web.php:88` "user ID 1 only" comment. This is standing `User`-table hygiene independent of D16 — Phase 5's write surfaces touch `letter_templates` and `settings`, never the `User` table — so it lands as its own commit and is **not** part of the Phase 5 build.

#### Surface A — Template editor (`letter_templates`)

A form to edit the master wording of letters, replacing phpMyAdmin (which is too crude for prose, and mishandles quotes/apostrophes without escaping knowledge).

- **A1 — Letter text is always admin-editable. No lock, including on solicitor-reviewed rows.** The solicitor sign-off is not a freeze; wording must always be changeable without a release. To make edits evidential rather than silent, **every edit is retained in the letter text change-history table** (version, editor, timestamp, before→after) — not a boolean "drifted" flag. The history table is the record of how the wording-of-record changed over time. *Rationale:* an evidential product cannot have its letters silently altered with no trace, but neither can it require a code release to fix wording. Full history reconciles both.
  - *Table:* the **letter text change-history** table — versioned, with full letter-body before/after (TEXT). Columns: template ref, version, editor, timestamp, before-text, after-text. The snake_case identifier is CC's at build, following existing schema convention (e.g. `letter_text_change_history`).
- **A2 — Token validation blocks the save.** A save is rejected if the edited text drops or misspells a placeholder (e.g. `{{notice_numbers}}`, `{{issue_desciption}}`, `{ {notice_number}}`) against the known placeholder whitelist. *Rationale:* the edit is free-text typed by a human; a fat-fingered token would later render blank into live sends — the admin-introduced recurrence of the old blank-description defect (snags #3 / #11). Validation is at **template-save time only**; it never touches a case. By the time a token reaches a case it is already resolved and frozen in `case_messages` — there is no token left to misspell and no editing of it.
  - *Whitelist source:* read **live from the renderer's existing whitelist at validation time** — not copied into the validator. Copying creates whitelist drift the first time a placeholder is added. (D0 mechanical detail: confirm the renderer exposes the whitelist; if not, expose it.)
- **A3 — Mandatory rendered preview before a template edit goes live.** Reuses the existing `LetterTemplateRenderer`. Not optional.

**Invariant preserved:** a template edit affects **future sends only**. Letters already sent stay frozen in `case_messages`; the editor must never retroactively alter sent correspondence.

#### Surface B — Settings editor (`settings`)

A form to edit intervals, caps, and ladder lengths.

- **B1 — Hard reject out-of-range values.** Values that would break the sweep (e.g. `escalation.interval_days = 0`, `escalation.max_notices = 0`) are rejected on save, not soft-warned. *Rationale:* a setting that stalls the sweep is a production incident; guard against the idiot mistake.
- **B2 — Interval changes apply to in-flight cases behind a flag that ships OFF.** The behaviour: thresholds read live at sweep time, so a changed interval would reach cases already mid-clock at the next sweep. This behaviour is wrapped in a single global flag — **"Applies to In-flight cases"** — which **ships set to No (Off)**.
  - *Ruling (21 Jun), reversing the earlier default-ON lean:* default is **Off**. The reasoning the earlier ON lean missed — ON *poisons* observability rather than aiding it. If a setting can change mid-flight, no case's pacing can be read without cross-referencing *when the interval changed* against where each case sat in its clock at that moment; every case becomes "14 days until day 9, then 7 after," reconstructed from two tables. **Off** means each case runs its whole life under one set of intervals, so what is observed is what actually governed the case — the clean experiment the family pilot needs. The flag remains so the live-read behaviour can be switched **On** later without a code change, but it does not ship On.
  - *Flag storage:* a single value in the settings table (the **interval-settings** table). One boolean, human-readable key — "Applies to In-flight cases".
- **B3 — Settings changes are audit-logged in their own table.** Every setting change writes one row: setting key, editor, timestamp, old value → new value.
  - *Ruled (21 Jun):* this is a **separate table** from A1's letter text change-history — **not** a merged mechanism, **no `subject_type` discriminator.** The two share a conceptual shape (who changed what, when, old→new) but not a storage shape: A1 is versioned with full letter-body before/after (TEXT); this log is flat scalars with no version concept. Separating keeps every column meaningful for every row in each table. Governing reason: clean history separation.
  - *Table:* the **interval-settings-hist** table. **Full audit shape, old and new value on the same row** — one row is one complete, self-contained change (key, who, when, old-value, new-value), readable without reference to neighbouring rows. "Who" is recorded even though it is a single admin for the foreseeable future — the column costs nothing now and spares a schema change when a second admin appears. Err expansive: unkept history is unrecoverable, over-kept history is harmless. Snake_case identifier is CC's at build (e.g. `interval_settings_hist`).

#### Surface C — Case oversight (read-only)

Admin visibility into cases: state, ball, clock position, next-sweep projection, and the `case_events` trail. **Read-only. No force-transition, no case-field editing through the UI.**

- *Rationale:* every state change is supposed to have a recorded cause in `case_events`. A UI that lets an admin force a transition is a hole in the evidential spine. There is no demonstrated need for admin intervention yet, and each forced transition is a new way to corrupt a case's narrative or violate a machine invariant.
- **Break-glass:** a genuinely stuck case is adjusted in extremis via phpMyAdmin. This is accepted *because* it is rare and manual — the friction is the safeguard that stops case intervention becoming routine. Such an adjustment bypasses `case_events` and will leave no recorded cause; acceptable for a documented break-glass action, unacceptable as a routine UI affordance, which is the whole reason C is read-only.
- **Exhausted cases render record-only** (see #21 ruling below): a case in the terminal `escalation_exhausted` state shows its state and frozen record with **no live controls** — no "Abandon" action, no live stance dropdown. Surface C's case list and case view must reflect this.
- If the pilot later surfaces a real need, the minimal safe extension is per-case next-sweep timing only (never arbitrary transitions) — deferred, not built speculatively.

#### Snags folded into Phase 5

These are addressed in the Phase 5 build because they are direct dependencies or share a root with a Phase 5 surface:

- **#16** — `"Stage N of 4"` hardcodes the denominator. **Hard dependency of Surface B:** the moment the settings editor can change `escalation.max_notices`, the literal `4` silently lies. Must read `escalation.max_notices` live.
- **#14 / #15 / #21-tail** — "Next escalation" shown on `on_hold`; stale `hold_until` after release; "Next escalation" reappearing on the abandoned/exhausted card. One shared **state-aware-display predicate**; Surface C's next-sweep projection must use the same predicate or it reinvents this bug family. Fix together. The predicate shows "Next escalation" only while the case is `awaiting_landlord` with the clock live; it therefore suppresses the line on `on_hold`, dormant, the closed states, and exhausted alike. "No live actions" applies to the truly-closed states (`resolved`/`abandoned`) only — an exhausted case keeps its D14 actions (reply/resolve/abandon); see #21.
- **#21 — RULED 21 Jun (revised): resolve the abandon-collision; revival preserved (Option C).** The exhausted case page exposed two controls both reading as "abandon" — the mechanical "Abandon this case" action (a real terminal close, D5/D14) and the cosmetic `exhausted_stance` dropdown whose values included "Abandoned". The fix is **UI-only: remove the cosmetic stance dropdown** (the colliding control); keep reply / resolve / abandon exactly as D14 has them. **D14 is not reversed** — an `escalation_exhausted` case stays revivable (a late landlord reply, or a tenant reply, revives it) and closable. No backend, policy, or state-machine change: the `ExhaustedStance` enum and the `setStance` action remain in the codebase, dormant (no UI), pending a future disposition. *(An earlier draft framed exhausted as "dead/terminal/no-controls"; that was wrong against the implemented and tested D5/D14 machine — `escalation_exhausted` has four outgoing transition edges, and only `resolved`/`abandoned` are terminal — and is withdrawn.)* This ruling supersedes both conflicting #21 blocks in the snag file.
- **#20** — D9 header block invisible in dark mode (`buildHeaderBlock`). If Surface A's preview renders through that block, the same bug appears; fix once, covers both.
- **#4 — human-quotable case reference. RULED 21 Jun: 6-char uppercase, 32-char alphanumeric alphabet (A–Z + 2–9, excluding I, O, 0, 1).** ~1.07bn range; ambiguous read-aloud characters removed; digits mixed in, so no profanity/near-word filter is needed. Surface C's case list displays references, so build with Surface C so the list shows the final format. Existing seed-data references are regenerated to this format (only seed data exists pre-flip — no real-reference migration).

#### Explicitly NOT Phase 5

- **#8 (delivery-status webhooks)** → **its own pre-flip evidential-hardening phase**, not an admin-UI panel. Rationale: with the D2 ruling that the **tenant** is notified of an undeliverable outcome (an undeliverable letter is the tenant's business — it directly breaks the "your landlord was notified, here's the proof" promise), #8 has grown a tenant-facing notification + act-on-it flow that is a design surface in its own right. That phase becomes the natural home for the other delivery/evidential issues expected to surface, and is the pre-flip companion to solicitor sign-off. Mechanism reuses the existing HMAC-verified inbound webhook pattern.
- **Admin-security hardening** (`is_admin` `$fillable` removal + regression test + stale-comment fix) → **its own commit** (see Auth section). Not an admin-UI surface; standing `User`-table hygiene independent of D16.
- **Tooling / CLI snags** (#9, #10, #13, #17) → dotrent / tooling pass.
- **Tenant-facing UI / features** (#1, #2, #7, #19) → separate tenant-UI track.
- **#18** (implicit `ON UPDATE CURRENT_TIMESTAMP` trap) → confirmed **not** triggered by Phase 5's two write-tables (`settings`, `letter_templates` are not on the trap list). Note and move on.
- **`letter_templates.active` toggle** (admin activate/deactivate) → **deferred, stays phpMyAdmin-only.** Surface A is wording-only. `active` is load-bearing on the sweep (`LetterTemplate::forEscalation` / `firstActiveOfType` decide send-vs-skip on it), and deactivating a template mid-escalation has **undesigned in-flight semantics** (skip? stall? error?). Same logic as Surface C's break-glass — the phpMyAdmin friction keeps a consequential, under-designed action rare and deliberate. Named later gap, not a Phase 5 build: candidate for the post-#8 machine-state-UI ruling, once in-flight semantics are designed.

#### Open for the build D0 / CC brief (genuinely mechanical — no design judgement left)

- The exact placeholder whitelist A2 validates against — source of truth is the renderer's existing whitelist, read live (confirm the renderer exposes it).
- Per-table column names / migration shape for the two history tables (letter text change-history, interval-settings-hist) and the snake_case table identifiers.

#### Closed since the previous draft (no longer open)

- ~~Whether A1's table and B3's log are one mechanism or two.~~ **Ruled: two separate tables, no `subject_type`.** (Surface B / B3.)
- ~~B2 default ON or OFF.~~ **Ruled: ships OFF ("Applies to In-flight cases" = No).** (B2.)
- ~~`is_admin` auth groundwork in place?~~ **Confirmed: in place. Phase 5 is screens, not gatekeeping.** (Auth section.)
- ~~#21 abandon-collision.~~ **Ruled: Option C — remove the colliding cosmetic stance dropdown (UI-only); D14 revival/close preserved, no backend change.** (#21.)
- ~~#4 reference format.~~ **Ruled: 6-char, A–Z + 2–9 minus I/O/0/1.** (#4.)

### D17 — Delivery failure: silence must mean "delivered and not answered"

**Ruled by Charlie 2026-08-09.** Full reasoning and the nine rulings R1–R9
are in `delivery-failure-design-question.md`; the D0 verification is
`cc-report-delivery-events-d0.md`. This section exists because everything
above **assumes a sent letter arrives**, and that assumption is now
explicit rather than silent.

**The gap.** Mailgun's inbound route is consumed; its delivery-event
webhooks are not. A letter that bounced and a letter that was ignored are
indistinguishable to the system — so the model's load-bearing claim
("served on the 12th, no response in 14 days") is stated with full
confidence when nobody was ever served. Observed twice on gafol (6 and 7
June 2026): sends accepted by Mailgun, blocked at Gmail, the platform
showing nothing.

**D17.1 — the record EXTENDS, it does not break.** A bounce does not
invalidate what came before. *We sent it · we detected a bounce · we
informed the tenant · we stopped · a new address was given · the case
restarted* — each separately true, each its own entry. Nothing is ever
retracted.

**D17.2 — hard stops, soft is silent.** A **permanent** failure stops
the case and notifies the tenant. A **temporary** failure is recorded
and produces no tenant-facing action; Mailgun retries and most deliver.

*Amended 2026-09-03 against observed payloads.* This section previously
named `permanent_fail` and `temporary_fail`. **Those events do not
exist.** Mailgun sends one `failed` event carrying
`severity: permanent | temporary`. A receiver keyed off the names the
subscription UI displays would match nothing, and the ladder would run
on regardless. **Branch on `severity`, never on the event name.**

Two different things arrive as `failed`/`permanent` and are told apart
by **`reason`**: `generic` is a real bounce that was attempted;
`suppress-bounce` is a send Mailgun dropped without attempting, because
the address is already on our suppression list. Both stop the case —
neither was delivered — but only the second means earlier letters have
been silently swallowed. `reason` is recorded on the event.

**Do not infer suppression from severity.** An observed `permanent`
carried `bounce-type: soft` (a domain that does not resolve) and did NOT
add the address to the suppression list. Severity is a fact about this
message; suppression is a fact about the address.

Evidence: `mailgun-delivery-event-payloads.md`, three real prod sends,
23 Aug 2026.

**D17.3 — a letter-1 bounce forks, and the TENANT takes the fork.** The
old case closes terminal. The tenant is offered a **copy** of it — same
property, category, severity, description and photos — which they
accept, and which drops them into the ordinary create-case flow at the
preview step. The copy has **zero message rows**, so its derived counter
is genuinely 0 and its first letter is stage 1. **Nothing is reset and
D3's ratchet is untouched** — the numbers are simply true. No link is
stored between the two cases.

*Amended 2026-09-03 (ruled by Charlie).* As first written this read as
an automatic fork at bounce time, and #24 has since made that unsafe: a
case inherits the property's CURRENT landlord contact with no per-case
override, and creation sends immediately (`CaseController::confirm`). At
bounce time the property's current contact is still the address that
bounced — so an automatic fork would send to the dead address, bounce,
and fork again. A loop of real sends, real events and real tenant mail.

Tenant-initiated closes that, and buys three things: the D13 preview
stays in the path, so #59's landlord-email display does its job; no
second send path is created; and nothing exists that could sit
unclaimed forever.

**The preview is the guard.** Where the resolved landlord email equals
the address on the bounced message, the preview REFUSES to confirm and
says so, linking to the property's landlord-contact correction. Correct
it, return, confirm, send. A tenant who corrects the address before
taking the copy passes straight through.

**This does not make escalation contingent on tenant attention** — the
prohibition that removed an earlier judgement step. No clock pauses and
no case waits: the old case is already terminal, and the copy escalates
on its own once raised.

**D17.4 — mid-flow bounces are OUT OF SCOPE.** Continuing after a
mid-ladder bounce would require re-serving the bounced rung, writing a
second row at the same stage and inflating the row-counted ladder.
Deliberately deferred. Mid-flow still detects, records, notifies and
stops; only the automated correct-and-continue is absent.

**D17.5 — complaints are terminal wherever they occur, and never fork.**
There is no address problem for a new address to fix. Note the evidential
asymmetry: a bounce proves the letter went **nowhere**; a complaint proves
the **opposite** — it arrived, was seen, and was rejected. That is
evidence of receipt.

**D17.6 — failure reasons are NOT case statuses.** Two collections:
delivery outcome is a fact about **one message** and lives on
`case_messages`; case status is where the **conversation** stands. One new
terminal status `contact_failed` means "email contact with this landlord
cannot continue"; *why* is read from the message.

The reason is cost asymmetry, evidenced by snag #47: statuses must be
classified in every predicate and were previously fail-open, whereas no
predicate branches on a message column. **Statuses are expensive and
dangerous; message columns are cheap and inert.**

**D17.7 — `delivered` events are captured too**, giving a
signature-verifiable record of an external event that outlives Mailgun's
one-day log retention.

**D17.8 — which statuses may enter `contact_failed`, and how it ends.**
*Ruled by Charlie 2026-09-04.* `RepairCase::TRANSITIONS` is the single
source of truth and only `transitionTo()` may change status, so this
must be enumerated rather than inferred. A bounce arrives
asynchronously, so the case may have moved on by the time the event
lands.

**MAY enter `contact_failed`** — the case is still running, and a letter
that went nowhere stops it:
`open`, `awaiting_landlord`, `awaiting_tenant_review`, `on_hold`,
`dormant`.

**MAY NOT — record the event, do not transition:**
`escalation_exhausted`, `resolved`, `abandoned`. These have already
stopped, by exhaustion or by a decision someone made. A late bounce must
not reopen them or rewrite why they ended.

**The principle:** a bounce stops a case that is still running; it does
not reach back into one that has already stopped. Nothing is lost by the
distinction — the `case_events` row is written either way, per D17.1 the
record extends rather than being retracted.

**Exit: `contact_failed` → `abandoned` is ALLOWED.** The tenant may close
their own case, exactly as `escalation_exhausted` permits. The evidence
lives in `case_events`, not in the status, so closing it retracts
nothing. Ruled as the recoverable direction: a case stuck with no exit is
worse than one whose exit is later reconsidered. Charlie's reasoning —
*"I'll know better when I've used it"* — so revisit after real use rather
than treating this as settled forever.

**No other exit.** `contact_failed` does not revive on a tenant or
landlord reply the way `dormant` and `escalation_exhausted` do. The
address is broken; a reply from it is not expected, and D17.3's copy is
the route forward.
**D17.9 — receiver mechanics.** *Ruled by Charlie 2026-09-05.* Four
details the D0 left open, settled before the controller was written.

**Event names.** A permanent failure writes `delivery_failed` (fixed by
the #25 ruling). Its counterpart is **`delivery_confirmed`** — a fact
about a mail server accepting the message, never about anyone reading
it, per D17's wording discipline.

**An event we cannot match to a message: accept it (200) and log.** Every
outbound letter carries `case_message_id` as a Mailgun custom variable,
but a stale or foreign event may not resolve. Returning an error would
make Mailgun retry for hours against a payload that will never match on
any attempt. Accept, log, do nothing.

**Repeated deliveries are ignored, keyed on Mailgun's own
`event-data.id`.** Mailgun re-sends when unsure a webhook was received.
Without this, one bounce could write two `case_events` rows or notify a
tenant twice. NOT in the D0 — found while building. The check is scoped
to the case the event resolves to, so it is a small read, not a table
scan.

**A `temporary` failure still writes its `case_events` row.** D17.2 rules
it produces no tenant-facing action; that is about the tenant, not about
the record. Silent to the tenant, visible in the evidence — "we tried,
here is what happened each time" is exactly what the record is for.
**Consequences for the model above:** the escalation counter and D3 are
**not modified in any way** by D17. The tenant notification is
**mail-only** and writes no `case_messages` row — an outbound system row
with a non-null `stage_at_send` would inflate the ladder.

**Wording discipline (binding).** "Delivered" means accepted by a server,
never read. The accepting server is the MX for the **recipient's domain**
(Google, Microsoft), not "the landlord's mail server". A complaint is
"reported as spam", not "the landlord marked it as spam" — the event
arrives via the provider's feedback loop and does not tell us who clicked.
Bounce handling catches "went nowhere", never "went somewhere wrong".

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
   CaseController::sendNext + UI (D7 resolved). Nudge sends
   remain mail-only, never case_messages rows (evidential invariant).
   Create-case flow gains the notice-1 preview + confirm step and the
   one-time authorisation wording per D13. Ride-along snags: #1 (nav
   title), #9 (shadow-log truncation), #10 (sweep summary tally).
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

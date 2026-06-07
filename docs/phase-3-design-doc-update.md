# Design doc update — Phase 3 decisions (Sun 2026-06-07)

Edits to `docs/llcs-silence-model-design.md`. Decisions agreed in design
session Sun 2026-06-07. Apply as specified; flag conflicts.

---

## 1. REPLACE §1 "The model in one paragraph" with:

> Both parties correspond freely. Silence — and only silence — drives the
> machinery: landlord silence fires escalation letters (formal, evidential,
> ratcheting); tenant silence fires private nudges sliding toward dormancy.
> Cases end by tenant decision (resolved / abandoned), by the dormancy
> timer, or by the escalation ladder running out
> (`escalation_exhausted`), at which point the platform's job becomes
> signposting external remedies and handing over the evidence bundle.
> The tenant has three actions: reply, resolve, abandon (plus an explicit
> pause). Everything else is the clock. The platform never judges message
> content — not the landlord's, not the tenant's.

---

## 2. REPLACE D7 (currently OPEN) with:

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

---

## 3. ADD new decisions D8–D12:

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

## 4. §4 Schema changes — ADD rows:

| Change | Notes |
|---|---|
| `cases.description` | Tenant's original framing, set at creation, immutable (D9) |
| New table for magic-link tokens | Single-use, expiring, per-tenant (D12) — shape is CC's call |
| `settings` rows | `dormancy.revival_days` = 90 (D11), `hold.max_days` = 60 (D10) |

---

## 5. §8 Phase 3 outline — REPLACE the Phase 3 entry with:

> **Phase 3 — Tenant reply + tenant-side go-live.** Reply UI + controller
> per D8; reuses outbound letter machinery; the original snag closes.
> Tenant-side silence handling (nudges, dormancy sequence) goes LIVE —
> nudges finally have an action to point at. on_hold wired as explicit
> pause per D10. `cases.description` per D9 across all outbound mail.
> Magic-link sign-in per D12 on all touched emails. Dormancy revival
> window per D11. DEMOLITION: SweepDormancy, SweepHolds (absorbed by
> silence:sweep), CaseController::sendNextNotice + UI (D7 resolved).
> Nudge sends remain mail-only, never case_messages rows (evidential
> invariant). Ride-along snags: #1 (nav title), #9 (shadow-log
> truncation), #10 (sweep summary tally).

---

## 6. Snag list dispositions (for the snag file, separate commit):

- #3 → closes into D9 (supersede note, point at D9)
- #4 (half-duplex entry) → superseded by D8 / Phase 3
- #5 → superseded by D12
- #6 → closed by D12
- #11 → closes into D9
- #1, #9, #10 → note "scheduled: Phase 3 ride-along"
- #4a (short references), #7 (landlord lookup), #8 (delivery webhooks)
  → unchanged, still open
- #2 → still open, entry incomplete

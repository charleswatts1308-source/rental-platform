# CC BRIEF — D15: Engagement-Gated Escalation

**Read first:** CLAUDE.md, the design doc
(`docs/llcs-silence-model-design.md`, D1–D14 — authoritative; conflicts
→ design doc wins, flag them), this brief, and your own D15 fact-find
report.

**Discipline:** report first, edit second. Deliverable 0 before any
code changes. Stop after D0 and wait for go-ahead.

**Sequencing note:** this lands BEFORE Phase 4. It closes a live-harm
gap (escalation fired at a landlord on a case the tenant considers
resolved — confirmed in your fact-find §4). Phase 4 (escalation_exhausted)
waits behind it.

---

## The problem (from your fact-find, confirmed in code)

A tenant reply is uniform: every reply → `awaiting_landlord`, clock
restarts (§3). So a "thanks, all sorted" reply from
`awaiting_tenant_review` lands in `awaiting_landlord`, restarts the
landlord clock, and after 14 days of (entirely expected) landlord
silence the sweep fires a real escalation letter at a landlord on a
case the tenant treats as closed (§4). That is the harm. It exists
ONLY where the landlord has engaged — a "thanks" reply presupposes
something to thank them for.

## Governing principle

The platform is a force-multiplier for tenant effort, not a substitute
for it. **If the tenant slacks off, so do we** — EXCEPT against a
landlord who has never engaged, where the platform carries the pursuit
alone (the tenant has had no opening to spend energy, and authorised
the pursuit once at case creation).

## The rule change — two landlord classes

A new one-way fact governs whether escalation auto-fires:
**has this landlord ever replied on this case?**

- **Never-engaged** → escalation stays FULLY AUTOMATIC, exactly as
  today. Legitimacy: the D13 create-case authorisation is standing
  consent to pursue a silent landlord. The tenant is NOTIFIED on every
  auto-send (see below) to maintain attention — informational only.
- **Engaged-then-quiet** → escalation becomes TENANT-GATED. The sweep
  does NOT auto-send the landlord notice. Instead it surfaces the
  prepared notice to the tenant to authorise (reuse the D13
  preview/authorise pattern), and nudges the TENANT — not the
  landlord. If the tenant never authorises, the case falls to the
  EXISTING nudge → dormancy tail (D11 revival applies). We do not chase
  harder than the tenant's will.

Posture maps to who carries the energy: machine acts for you → it
TELLS you; your will should govern → it ASKS you.

This is a deliberate, partial reversal of D7's demolition of the
tenant "send next notice" click — on legitimate new grounds (the
thank-you harm post-dated D7). It also properly closes D7's other open
case: the engaged-but-REFUSING landlord is an engaged landlord, so
tenant-gated re-push is his home too.

---

## Deliverable 0 — report, no edits

Enumerate, citing file + line, then stop:

**D0.1 — the engagement flag.**
- Proposed column on `cases` (name, type — propose `landlord_engaged`
  boolean, default false). Migration shape.
- The SINGLE chokepoint where a real landlord inbound is processed and
  the flag flips false→true (your fact-find pointed at the inbound
  webhook path — confirm it is one place, name it). Confirm the flip
  is idempotent and never resets.
- **Critical definition question:** what counts as "engaged"? A genuine
  inbound landlord reply only. Explicitly rule OUT: auto-responders /
  out-of-office (flag as a known soft edge — at pilot scale, tolerable,
  and the failure is SAFE: a wrongly-engaged case becomes tenant-gated
  rather than auto-escalating, i.e. it under-pursues, never
  over-pursues). Report how inbound is currently classified and whether
  any reply-vs-bounce distinction already exists.

**D0.2 — the verdict branch.** The change to `landlordSideVerdict`
(`SilenceClock.php:233-260` per your report): when the clock would
return SendEscalation, branch on `landlord_engaged`. Engaged →
withhold + mark "tenant authorisation required" (NOT a new state —
see D0.4). Never-engaged → SendEscalation as today. Report the exact
shape and every call site that consumes the verdict.

**D0.3 — does this need a new state or not?** Strong prior: NO new
state. "Tenant authorisation required" should be representable without
adding to `CaseStatus`. Report whether the held-notice condition can
live as a flag / pending-authorisation record alongside the existing
`awaiting_landlord` (or wherever the case sits), and what the tenant's
case page keys off to show the authorise prompt. If you believe a new
state is unavoidable, argue it — but the bar is high (a new state
means new sweep-exclusion wiring, per your §6).

**D0.4 — the tenant authorise action.** How the D13 preview/authorise
pattern is reused to send a WITHHELD escalation notice (vs D13's
create-case first notice). Same controller shape? Same view? Report
the reuse and what is genuinely new.

**D0.5 — the tenant-side nudge for engaged-quiet.** The existing nudge
ladder already nudges silent tenants in `awaiting_tenant_review`. For
the engaged-quiet held-escalation case, the nudge must point at the
AUTHORISE action ("your landlord has gone quiet — send the next
notice?"). Report whether this reuses the existing nudge machinery
with different wording/target, or needs new logic. Confirm the
unauthorised tail is the EXISTING dormancy path, not a new one.

**D0.6 — notify-on-send (never-engaged).** Your fact-find / 2b notes
that auto-escalation already notifies the tenant once per fired notice
("active-template-row idiom"). CONFIRM this exists and fires on EVERY
auto-escalation to a never-engaged landlord. If it exists: we are
confirming, not building. Report:
- that it is informational only — NO action, NO button;
- that it does NOT move the ball, start a tenant clock, or write a
  `case_messages` row (same evidential invariant as nudges — mail
  only, never a turn);
- if it does NOT already exist or is threshold-gated, report what
  it would take to make it every-send.

**D0.7 — backfill / existing cases.** With `migrate:fresh` from files
at pilot, all cases start with the flag set correctly from creation —
non-issue. CONFIRM. Note for the record: if live cases were ever
migrated, an unset flag defaulting false would (safely) make an
engaged case auto-escalate; backfill would need history inspection.
One line in the design note; no code now.

**D0.8 — template / settings rows.** New wording rows (data, not code):
the tenant authorise-prompt nudge, the authorise-screen copy. Flag
which want Charlie's eyes / solicitor pass. List them; do not finalise
wording.

**D0.9 — test surface.** Enumerate the new/changed tests:
- flag flips on first genuine landlord reply, never resets;
- never-engaged case still auto-escalates on landlord silence;
- engaged-then-quiet case WITHHOLDS escalation and nudges the tenant
  instead;
- tenant authorise fires the held notice through the outbound path;
- unauthorised engaged-quiet case falls to the existing dormancy tail;
- notify-on-send fires on every never-engaged auto-escalation, writes
  NO `case_messages` row, moves NO ball, starts NO clock;
- the thank-you path: reply from `awaiting_tenant_review` on an engaged
  case does NOT result in an auto-fired escalation (the headline
  regression test).
Disposition any existing escalation tests that the verdict branch
changes. No weakened assertions.

**D0.10 — ride-along check.** Does this interact with the open snags
#12–19? Flag any overlap (esp. anything touching escalation display or
reply handling). Do not fix here.

Stop after D0. Wait for go-ahead.

---

## Scope walls

**In scope:** the flag, the verdict branch, the held-notice +
authorise reuse, the tenant-side nudge retarget, notify-on-send
confirmation, wording rows, tests.

**Explicitly untouched:**
- Phase 4 `escalation_exhausted` — next, separately.
- Phase 5 admin UI.
- The success-recording strand (satisfied case → dormant rather than
  resolved) — a SEPARATE design question, not this brief. Do not fold
  it in.
- Content/intent analysis of email text — parked, advisory-only,
  post-pilot. Not in any control path.
- Snags #12–19 — separate batch.

## Acceptance (for the build phase, after D0 is ruled)

1. Suite green; count against current baseline (448).
2. gafol live-fire — **landlord-side re-proof required.** This touches
   the live 2b escalation engine, so the landlord-side live-fire is
   re-run: never-engaged case auto-escalates AND notifies; engaged case
   withholds, nudges tenant, authorises, fires; thank-you reply on an
   engaged case produces NO escalation. Branch + runbook as per the
   2b/3 pattern.

---

## The headline, one line

Escalation stops being unconditionally automatic. It stays automatic
against a landlord who has never engaged (with a notify heartbeat to
the tenant), and becomes tenant-authorised against a landlord who
engaged then went quiet. The tenant can no longer, by error or
inaction, CAUSE a wrongful escalation — the worst they can do is fail
to authorise one, which is the safe failure.

Begin with Deliverable 0.

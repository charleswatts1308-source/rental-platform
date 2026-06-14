# CC BRIEF — Phase 4 / D14: escalation_exhausted

**Read first:** CLAUDE.md, the design doc
(`docs/llcs-silence-model-design.md`, D1–D15 — authoritative;
conflicts → design doc wins, flag them), this brief.

**Discipline:** report first, edit second. Deliverable 0 before any
code. Stop after D0 and wait for ruling.

**What this concludes:** the last case state the silence machine
doesn't yet handle. After Phase 4, every terminal outcome has a home.
This is the close of the escalation/email cycle.

---

## D14 — the ruling (already decided; build to it, don't re-litigate)

When a case reaches `escalation.max_notices` and the landlord is STILL
silent, the sweep promotes the existing `transition_exhausted_intent`
(logged in shadow since 2b) to a REAL transition into a new terminal
state `escalation_exhausted`. The ladder stops climbing.

- **Allow-reply:** a reply from `escalation_exhausted` revives to
  `awaiting_landlord` (fresh token, frozen message, clock restarts) —
  the landlord re-engaging is the wanted outcome. Same shape as the
  dormancy-revival reply.
- **No auto-revival, no further escalation.** Exits are a reply or the
  off-platform routes the signposting page points to.
- **Tenant stance flag — COSMETIC, label only, NO machine effect.** At
  end-of-road the tenant may choose **Abandoned** or **Unresolved**,
  or leave it unset (NOT forced). Three values: unset / abandoned /
  unresolved. It sets a displayed label and nothing else — does not
  change state, ball, clock, or sweep behaviour.
- **One notice template row** (data): tells the tenant the ladder is
  complete, links to the signposting page. Solicitor wording.
- **One members-wall signposting page**, stubbed near-empty: shape
  only ("if the landlord won't engage, your documented trail supports
  these routes…"), NO named bodies, thresholds, or deadlines. Wording
  deferred to the solicitor pass. Linked from the exhausted notice;
  NOT in public nav.
- **Sweep-inert:** an `escalation_exhausted` case is excluded from BOTH
  escalation AND nudge evaluation, at ALL THREE stance values — no
  silence accrual, no pestering.

## How D15 tidied D14's scope (state explicitly; don't mis-wire)

D15 made escalation tenant-GATED for ENGAGED landlords (withheld →
nudge → dormant if unauthorised). So an engaged case never auto-climbs
to `max_notices` — it goes dormant via the D15 tail instead.
**Therefore `escalation_exhausted` is reachable ONLY by the
never-engaged path** — auto-escalation climbing the full ladder
against a landlord who never replied. The engaged-then-quiet case has
its own terminal (dormant). Do NOT wire exhaustion checks into the
engaged/held path — they can't fire there. Exhaustion is purely a
never-engaged terminal.

---

## Deliverable 0 — report, no edits

Enumerate, citing file + line, then stop:

**D0.1 — the intent today.** Where `transition_exhausted_intent` is
currently computed/logged in the verdict + sweep (per the D15 fact-
find it lived around `SilenceClock` landlordSideVerdict /
`SilenceSweep`). Show the exact condition (counter ≥ max_notices &&
landlord silent && never-engaged). Confirm it currently only LOGS and
takes no transition. Report what `escalation.max_notices` is in
settings.

**D0.2 — promote intent → transition.** The change to make the intent
fire a real transition into `escalation_exhausted`. Report the new
edge(s): which source state(s) → `escalation_exhausted` (expect
`awaiting_landlord → escalation_exhausted`). Mirror the D15
awaiting_landlord → dormant edge pattern (TRANSITIONS table +
positive test, removed from the illegal-transition dataset).

**D0.3 — the new state + sweep exclusion (the D15 §6 pattern).**
`escalation_exhausted` added to `CaseStatus`. It must be excluded in
BOTH gates exactly as resolved/abandoned/dormant are:
- the sweep query `whereNotIn('status', [...])` (was SilenceSweep
  ~:100);
- `NO_CLOCK_STATUSES` in SilenceClock (~:60).
Report both edits. Confirm the case is sweep-inert regardless of
stance value.

**D0.4 — allow-reply revival.** How a reply from `escalation_exhausted`
revives to `awaiting_landlord`. Reuse the dormancy-revival reply path
(D11) — same transition shape (fresh token, frozen message, clock
restart, ball → landlord). Report whether this is a new TRANSITIONS
edge (`escalation_exhausted → awaiting_landlord`) and whether the
existing reply handler (HandleInboundReply / the tenant reply path)
needs any branch, or whether it already routes any reply to
awaiting_landlord generically (D8) and just needs the edge legalised.
NOTE the D15 interaction: a revived exhausted case — does
`landlord_engaged` flip? A reply revival is a LANDLORD reply only if it
came from the landlord; a TENANT reply revives too. Report how revival
reconciles with the engagement flag, and FLAG if it's ambiguous — I
may need to rule it.

**D0.5 — the stance flag.** Propose the column (e.g.
`exhausted_stance` enum/string: null | abandoned | unresolved, default
null on `cases`). Confirm it is label-only: read by the UI, read by
NOTHING in the sweep/verdict/clock. Report where the tenant sets it
(the exhausted-case view) and where the label renders. It must be
impossible for the stance to affect machine behaviour.

**D0.6 — the notice row.** New `letter_templates` row (data): the
exhausted notice to the tenant ("the escalation ladder is complete…"
+ link to the signposting page). `type` per the existing idiom; seed
as a draft. Solicitor-pass wording — list it for the review set, do
NOT finalise. Report the merge fields it needs and confirm they're
available (don't invent — the last_reply_date lesson).

**D0.7 — the signposting page.** A members-wall content page, stubbed
near-empty. Report: the route, the controller/view, the members-wall
gate (consistent with "substantive content behind the members wall"),
and confirm NOT in public nav. Content is shape-only placeholder now;
real wording is solicitor-deferred. The exhausted notice (D0.6) links
to it.

**D0.8 — test surface.** Enumerate:
- never-engaged case at max_notices + landlord silent → transitions to
  escalation_exhausted (the headline);
- HEADLINE INERTNESS: an escalation_exhausted case, at EACH of the
  three stance values (unset/abandoned/unresolved), under the sweep →
  no_action, no escalation, no nudge, no silence accrual;
- allow-reply: a reply from escalation_exhausted revives to
  awaiting_landlord (token/message/clock per D11);
- stance flag sets label only — assert it changes no sweep outcome;
- engaged case does NOT reach escalation_exhausted (it goes the D15
  held → dormant route) — guard test confirming exhaustion is
  never-engaged-only;
- the new edges legal; illegal-transition dataset updated.
No weakened assertions. Report baseline (462) + new count.

**D0.9 — ride-along.** Any interaction with open snags #12–#20 or the
D15 code (esp. the verdict branch and the dormant edge). Flag, don't
fix.

Stop after D0. Wait for ruling.

---

## Scope walls

**In scope:** the new state, the promote-intent transition + edge,
both sweep exclusions, allow-reply revival, the label-only stance
flag, the notice row, the stubbed signposting page, tests.

**Explicitly untouched:**
- Phase 5 admin/config UI — next, separately, and unscoped.
- The success-recording strand (satisfied → dormant vs resolved) —
  separate, still unruled. Do NOT fold in.
- Content/intent analysis — parked, post-pilot.
- Snags #12–#20 — separate batch.
- Any change to the D15 engaged/held path — exhaustion does not touch
  it.

## Acceptance (build phase, after D0 ruled)

1. Suite green vs 462 baseline.
2. gafol live-fire: a never-engaged case driven the full ladder to
   max_notices auto-escalates each rung then transitions to
   escalation_exhausted; the exhausted case is sweep-inert at all
   three stances (Mailgun: nothing fired); a reply revives it to
   awaiting_landlord; the exhausted notice renders + links to the
   stubbed signposting page. Branch + runbook in the 2b/3/D15 pattern.
   (escalation_exhausted notice + signposting wording are solicitor-
   gated for production — draft is fine for the live-fire.)

## Headline, one line

The never-engaged landlord who ignores the entire ladder now reaches a
real terminal — escalation_exhausted — where the platform stops,
tells the tenant the automated route is complete, points them to their
options, and lets the tenant frame it (abandoned / unresolved) without
changing anything mechanical. A reply still revives it. This closes
the email cycle.

Branch `d14-escalation-exhausted` off main. No commits to main. Begin
with Deliverable 0.

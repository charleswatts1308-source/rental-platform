# D15 — Engagement-Gated Escalation — close-out

**Written:** Saturday 2026-06-13, ~13:03 local.
**Status:** CLOSED. Merged to `main` (`--no-ff`), gafol landlord-side
live-fire re-proof passed end to end, suite green on main.

This is the dated, immutable close-out. D15's living artefacts (design
doc D15 section, brief, D0 report, implementation report, snagging list)
keep their stable names in `docs/`.

---

## Merge

- **Branch:** `d15-engagement-gating` (off `main` at `52ecad2`).
- **Pre-merge tag on main:** `pre-d15` → `52ecad2` (pushed to origin —
  rollback anchor, matching the 2b/3 tags).
- **Merge commit on main:** `b4829fd` — *Merge d15-engagement-gating into
  main — engagement-gated escalation.* `--no-ff`.
- **Diff stat (merge):** 22 files, +2031 / −5.
- **Suite at merge:** 462 passed, 1102 assertions (run on main post-merge).
- **Branch:** retained (local + origin) — delete after the
  `escalation_authorisation` solicitor sign-off and the dotrent deploy
  confirm.

D15 lands **before** Phase 4. It closes a live-harm gap found after
Phase 3: a tenant's ordinary "thanks, all sorted" reply could cause a
wrongful escalation letter at a landlord the tenant considered done with.

## What shipped

**The rule change — two landlord classes.** A new one-way fact governs
whether escalation auto-fires: *has this landlord ever replied on this
case?*

- **Never-engaged** → escalation stays FULLY AUTOMATIC, exactly as
  before, with the existing per-send tenant notify (D0.6 — informational,
  no `case_messages` row, no ball move, no clock).
- **Engaged-then-quiet** → escalation is WITHHELD. The sweep surfaces the
  prepared notice for the tenant to authorise (D13 preview/authorise
  pattern) and nudges the tenant to authorise. If the tenant never
  authorises, the case walks the authorise-nudge ladder to dormant.

Posture maps to who carries the energy: machine acts for you → it tells
you; your will should govern → it asks you. The tenant can no longer, by
error or inaction, CAUSE a wrongful escalation — the worst they can do is
fail to authorise one (the safe failure).

**Mechanics (code):**
- `cases.landlord_engaged` (bool, default false), flipped false→true on
  ANY token-resolved inbound in `HandleInboundReply` (ruling 1 — generous
  definition fails safe), including quarantined; idempotent, never resets.
- `SilenceClock::landlordSideVerdict` branches on the flag when an
  escalation is due → `heldEscalationVerdict` (the held authorise-nudge
  ladder), landlord-ball throughout (ruling 2).
- `IntendedAction::SendAuthorisationNudge`; the sweep executes it
  (mail-only, no `case_messages`, clock NOT restarted) and walks to
  dormant via a NEW `awaiting_landlord → dormant` transition edge. D11
  revival window intact. **No new `CaseStatus`** — "authorisation
  required" is derived (`SilenceClock::authorisationPending`).
- Tenant authorise action (routes + controller + policy + view) reuses
  the D13 preview and the existing `SendCaseNotice` auto-escalation send
  path: counter ratchets (D3), letter frozen in `case_messages`, landlord
  clock restarts — identical to a sweep send.
- `{{last_reply_date}}` wired into the authorise-nudge from the landlord's
  most recent INBOUND message (not the latest message — that may be the
  tenant's own reply).

**Authority:** the design doc gained a **D15** section that supersedes D7
on new grounds (the thank-you harm post-dated D7; the authorise action is
a gated authorisation of a machine-prepared notice, not D7's banned
free-standing tenant trigger). D7 carries a back-pointer.

## Acceptance — disposition

1. **Suite green vs 448 baseline** — PASS. 462 passed (448 + 13 D15 tests
   + 1 last_reply_date test; the illegal-transition disposition is
   net-zero). No weakened assertions.
2. **gafol live-fire, landlord-side re-proof** — PASS (below).

## Live-fire results — gafol, 2026-06-13 (GREEN)

Every acceptance #2 item verified end to end on the live 2b escalation
engine:

- **Scenario 1 — never-engaged auto-escalates + notifies.**
  `send_escalation=1`; Mailgun showed landlord + tenant sends; stage
  ratcheted 1→2; the notify wrote NO `case_messages` and moved NO ball.
  The 2b engine survived the `landlordSideVerdict` branch unchanged.
- **2a — flag flips via the real chokepoint.** `landlord_engaged` 0→1
  through the live `HandleInboundReply` (not just the seeder); idempotent
  across repeated inbound; never resets.
- **2c / Scenario 3 — engaged-then-quiet WITHHELD (the headline).**
  Thank-you reply on an engaged case → sweep fired `send_escalation=0`,
  `send_authorisation_nudge=1`; stage held (no ratchet); Mailgun showed
  NOTHING to the landlord at the sweep; `authorisation_nudge_sent` logged
  as audit only (no ball move, no clock restart). **The harm is closed,
  observed.**
- **2d — tenant authorised.** Via the D13-pattern preview → held notice 3
  fired, delivered to the landlord (BT inbox, Mailgun Delivered); stage
  ratcheted 2→3; clock restarted; normal posture resumed.
- **2e — unauthorised tail walked.** authorise-nudge 1 → authorise-nudge 2
  → `transition_dormant_intent=1` via the NEW `awaiting_landlord →
  dormant` edge (`exceptions=0`, edge legal); `dormant_at` stamped (D11
  90-day window anchored); stage NEVER advanced; ZERO escalation across
  26/40/54 days overdue. "If the tenant slacks off, so do we" — live.
- **Idempotency.** Immediate re-sweep → `no_action=4`, `executed=0`. No
  double-fire.
- **Pretend-safety.** `--pretend-today=+5wk` → forecast
  `send_authorisation_nudge=2` + `resume_from_hold=1`, `executed=0`. The
  NEW action and NEW edge both honour pretend-mode: forecast, fire
  nothing.

## Behaviour note — authorise-nudge pacing (by design)

The held case is landlord-ball, so the authorise-nudge ladder is paced on
the ESCALATION rhythm, not the tenant-nudge cadence. The first
authorise-nudge does NOT fire at `nudge.first_days` (10) — it waits for
the escalation clock (`escalation.interval_days`, 14) to expire, because
there is nothing to authorise until a notice is actually due. Confirmed
correct-as-is on the live-fire (sweep at 11 days = `no_action`; fired only
past 14). Which keys the intervals read from:

| Step | Governing key | Default |
|---|---|---|
| Ladder entry / first authorise-nudge | `escalation.interval_days` (gate) | 14 |
| Second authorise-nudge | `nudge.second_days` | 20 |
| Dormant transition | `nudge.dormancy_days` (+ both nudges sent) | 30 |

`nudge.first_days` is read but **inert for held cases** while
`escalation.interval_days` (14) > `nudge.first_days` (10). If a
pilot-pacing pass ever raises `first_days` above the escalation interval,
it becomes live for held cases — a one-line note now sits by the setting
in `SettingSeeder` so a future tuning pass isn't surprised. (Tenant-side
nudges always read `nudge.first_days` directly — unaffected.)

## Snags logged (no fixes this pass)

- **#20** (NEW) — the D9 description header block renders near-invisible
  in dark mode (low contrast; content correct, legible only when
  selected). Affects the authorise-preview and any dark-mode letter/mail
  view. Cosmetic; predates D15 (the block is D9/Phase 3).
- **#12** (pre-existing) — SPECS category/description mismatch in seed
  data, re-observed on the live-fire (dashboard "Heating and hot water"
  vs a damp/bathroom D9 block). Annotated on #12, not duplicated. Not D15.

## Pre-flip obligation (carried to dotrent / production)

- **`escalation_authorisation` solicitor sign-off.** The per-send consent
  copy shown on the authorise screen is seeded as DRAFT and is
  SOLICITOR-GATED for production — it is consent to send a formal legal
  letter in the tenant's name. This gates LAUNCH, not merge (same logic as
  the letter wording). The sibling `authorisation_required_nudge` wording
  is signed off (Charlie) for gafol + pilot and is NOT solicitor-gated.

## Open thread (unchanged, out of D15 scope)

- **Phase 4 — `escalation_exhausted`** (now unblocked; D15 landed first).
- **Phase 5 — admin UI.**
- Success-recording (satisfied case → dormant rather than resolved) — a
  separate design question, deliberately not folded into D15.
- Snags #4, #7, #8, #12–#20.

## Session bookkeeping

- `main = b4829fd` (the D15 merge commit), pushed to origin.
- `pre-d15` tag → `52ecad2`, pushed to origin.
- `d15-engagement-gating` branch tip → `5af61ee` (merged via `--no-ff`;
  retained, local + origin, pending solicitor sign-off + dotrent deploy).

# D14 — `escalation_exhausted` — close-out

**Written:** Sunday 2026-06-14, ~17:39 local.
**Status:** CLOSED. Merged to `main` (`--no-ff`), gafol live-fire green end
to end, suite green on main.

This is the dated, immutable close-out. D14's living artefacts (design doc
D5 implementation note, brief, D0 report, implementation report, runbook)
keep their stable names in `docs/`.

---

## Merge

- **Branch:** `d14-escalation-exhausted` (off `main` at `b20061b`).
- **Pre-merge tag on main:** `pre-d14` → `b20061b` (pushed to origin —
  rollback anchor, matching the 2b/3/d15 tags). `main` had not moved since
  the tag, so no `pre-d14-merge` was needed.
- **Merge commit on main:** `61defc6` — *Merge d14-escalation-exhausted into
  main — escalation_exhausted terminal.* `--no-ff` (parents `b20061b` +
  `df3b48f`).
- **Diff stat (merge):** 26 files, +2091 / −11.
- **Suite at merge:** 485 passed, 1183 assertions (run on main post-merge).
- **Branch:** retained (local + origin) — delete after the solicitor
  sign-off on the exhaustion wording and the dotrent deploy confirm.

D14 closes the email cycle: the last case state the silence machine didn't
handle. After D14, every terminal outcome has a home.

## What shipped

**The rule.** When a NEVER-ENGAGED landlord ignores the full ladder
(`counter >= escalation.max_notices`, clock expired), the sweep promotes the
long-shadowed `transition_exhausted_intent` to a real transition into a new
terminal state `escalation_exhausted`. The ladder stops climbing; the clock
stops permanently — no further automatic escalation letters, ever.

Reachable **only** by the never-engaged path: D15 makes an engaged case
tenant-gated below max (withheld → authorise-nudge → dormant), so it never
climbs to exhaustion. The exhaustion gate sits above the D15 engagement
branch in the verdict, and a guard test locks the invariant.

**Mechanics (code):**
- **New `CaseStatus::EscalationExhausted`**; both sweep exclusions — the
  `silence:sweep` query `whereNotIn` AND `SilenceClock::NO_CLOCK_STATUSES`
  — so the case is sweep-inert at every stance value.
- **Landlord closer fires on the transition** (design doc D5).
  `App\Actions\SendExhaustionCloser` renders + sends the active
  `exhaustion_landlord` row (`exhaustion_landlord_closer`) with
  `stage_at_send = NULL` — a real, frozen `case_messages` row that does NOT
  inflate the D3 counter — and mints a fresh reply token so a late landlord
  reply still routes home. **One-shot**: guarded on the absence of a prior
  closer, so a tenant-web revival that re-exhausts does not re-fire it.
  Active-row idiom: no active row → skip silently.
- **Allow-reply revival, both edges** (mirroring dormancy's split, no
  window): tenant web reply → `awaiting_landlord` (`tenant_replied`,
  `landlord_engaged` untouched); landlord email reply → `awaiting_tenant_review`
  (`inbound_received` via `HandleInboundReply`, which flips `landlord_engaged`
  true → the revived case is thereafter D15-gated). The landlord edge is
  mandatory — without it the webhook would record the inbound but strand the
  case sweep-inert with the ball wrongly on the tenant.
- **Label-only `exhausted_stance`** (`null | abandoned | unresolved`):
  written only by `CaseController::setStance` (POST `cases.stance`), read by
  the UI and by NOTHING in the sweep/verdict/clock/state-machine. Structural
  inertness, not conditional.
- **Tenant notice reused** — `tenant_exhaustion_notice` (no duplicate row).
- **Members-wall signposting stub** (`members.escalation-routes`, auth, not
  in public nav), reached from the exhausted case page + the notice.
  Solicitor-deferred content.
- **Phantom-date fix**: the projected "Next escalation" date is suppressed
  on the exhausted status card (the clock has stopped).

**Authority:** the design doc D5 stays authoritative. D14 added an
"Implementation note — Phase 4 / D14" *inside* D5 (extends, contradicts
nothing) and resolved the stale D8 table row. The brief
(`cc-brief-d14-phase4.md`) was built from the D14 discussion and had
WRONGLY omitted the landlord closer; D5 won — Charlie ruled the closer in,
plus the two-edge revival, the clock-stops reconciliation, and reuse of the
tenant notice.

## Acceptance — disposition

1. **Suite green vs 462 baseline** — PASS. 485 passed (+23: 12 D14 cases,
   6 transition-map rows, 3 illegal pairs, 2 auto-extended terminal-dataset
   rows; the 2b exhaustion test reworked net-zero to assert the real
   transition). No weakened assertions.
2. **gafol live-fire** — PASS (below).

## Live-fire results — gafol, 2026-06-14 (GREEN)

- **Headline transition.** A never-engaged case driven rung-by-rung to
  `max_notices` (notices 2→3→4 on the 1st–3rd expiries; the 4th expiry
  transitioned) → `escalation_exhausted` + the closer fired to the landlord;
  counter NOT inflated (closer is `stage_at_send=NULL`).
- **Sweep-inert at stance unset + unresolved** — confirmed cosmetic /
  label-only; nothing fired.
- **Re-exhaust single-sweep, ratchet-barred, one-shot closer holds** —
  `closer_count=1` after re-exhaustion; no new letters.
- **Both revival edges** — tenant-web → `awaiting_landlord` (engaged
  untouched); landlord-email → `awaiting_tenant_review` (engaged flipped;
  webhook NOT dropped).
- **Phantom-date absent** on the exhausted view throughout.

## Snags logged (no fixes this pass)

- **#21** (NEW) — duplicate abandon controls on the exhausted case page: the
  cosmetic stance value "Abandoned" sits next to the real terminal "Abandon
  this case" action (both deliberate — the latter per D5's "tenant can still
  close"), and the names collide, so a tenant can close the case when they
  meant only to label it. **Machine behaviour is correct; the UI is
  confusing.** Carries an open design question for Charlie (cosmetic-only as
  ruled, with disambiguated labels — vs the alternative "Abandon truly
  closes, Unresolved = stay exhausted" model, which would reverse the D14
  cosmetic ruling and lose the abandoned-but-revivable state). Triage, not a
  blocker.
- **#20** (carried from D15) — D9 description header near-invisible in dark
  mode (low contrast). The exhaustion notice uses the same header block.
  Cosmetic.

## Pre-flip obligation (carried to dotrent / production)

- **Solicitor sign-off on the exhaustion wording** — `exhaustion_landlord_closer`
  (landlord-facing legal letter), `tenant_exhaustion_notice`, and the
  `members.escalation-routes` signposting content. All seeded as DRAFT and
  SOLICITOR-GATED for production. This gates LAUNCH, not merge — same logic
  as the letter wording and the D15 `escalation_authorisation` copy.

## Open thread (out of D14 scope)

- **Phase 5 — admin UI** for templates + settings.
- Success-recording (satisfied case → dormant vs resolved) — separate,
  still unruled; deliberately not folded in.
- Snag #21 design question (above); snags #4, #7, #8, #12–#20.

## Session bookkeeping

- `main = 61defc6` (the D14 merge commit), pushed to origin.
- `pre-d14` tag → `b20061b`, pushed to origin.
- `d14-escalation-exhausted` branch tip → `df3b48f` (merged via `--no-ff`;
  retained, local + origin, pending solicitor sign-off + dotrent deploy).

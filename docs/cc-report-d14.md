# CC REPORT — Phase 4 / D14 — implementation (pre-merge)

**Branch:** `d14-escalation-exhausted` (off `main` @ `b20061b`; tag `pre-d14`
on main). **No commits to main.** Stopping here for acceptance before the
`--no-ff` merge, per CLAUDE.md.

**Suite:** **485 passed, 1183 assertions** (baseline 462 → +23). Zero
failures. No weakened assertions.

Built to the D0 enumeration **as corrected by the four rulings** (D5 wins).
The never-engaged landlord who ignores the whole ladder now reaches a real
terminal — `escalation_exhausted` — where the platform fires the landlord
closer, tells the tenant, points them to their options, lets the tenant
label the outcome without changing anything mechanical, and a reply from
either side still revives it.

---

## What shipped (by ruling)

**Ruling 1 — landlord closer fires (D5).** New action
`App\Actions\SendExhaustionCloser` renders + sends the active
`exhaustion_landlord` row on the transition, `stage_at_send = NULL` (D3
counter untouched), fresh reply token minted (a late landlord reply still
routes home → revival). Active-row idiom: no active row → skip silently.
**One-shot** (D5 "one-shot" + ruling 2's binding invariant): guarded on the
absence of a prior closer, so a tenant-web revival that re-exhausts does not
re-fire it.

**Ruling 2 — "clock stops permanently" = no further automatic letters.**
`escalation_exhausted` is in `NO_CLOCK_STATUSES` and excluded from the sweep
query. A reply may restart the clock, but the D3 ratchet (counter ≥ max
forever) means the verdict can only re-exhaust — never emit another letter.

**Ruling 3 — both revival edges + engagement reconciliation.**
- tenant web reply → `awaiting_landlord` (`tenant_replied`); `landlord_engaged`
  stays false.
- landlord email reply → `awaiting_tenant_review` (`inbound_received`, via
  `HandleInboundReply`); `landlord_engaged` flips true → the revived case is
  thereafter D15-gated. No revival window.

**Ruling 4 — reuse the tenant notice.** `executeExhaustionTransition`
dispatches the existing `tenant_exhaustion_notice` (no duplicate row).

---

## Files touched

**New:**
- `app/Enums/ExhaustedStance.php` — `abandoned | unresolved` (null = unset).
- `app/Actions/SendExhaustionCloser.php` — the D5 closer.
- `database/migrations/2026_06_14_090000_add_escalation_exhausted_to_cases_status_enum.php`
- `database/migrations/2026_06_14_090100_add_exhausted_stance_to_cases_table.php`
- `resources/views/members/escalation-routes.blade.php` — signposting stub.
- `tests/Feature/Phase4/EscalationExhaustedTest.php` — 12 cases.

**Changed:**
- `app/Enums/CaseStatus.php` — `EscalationExhausted`.
- `app/Models/RepairCase.php` — fillable + `exhausted_stance` cast;
  `awaiting_landlord → escalation_exhausted` edge; the
  `escalation_exhausted` source state (both revival edges + close edges).
- `app/Services/Silence/SilenceClock.php` — `NO_CLOCK_STATUSES`.
- `app/Console/Commands/SilenceSweep.php` — query exclusion; inject the
  closer; `TransitionExhaustedIntent` → `executeExhaustionTransition` +
  the `exhaustionCloserAlreadySent` one-shot guard.
- `app/Actions/HandleInboundReply.php` — `EscalationExhausted` in
  `STATES_THAT_TRANSITION` (landlord-email revival).
- `app/Actions/SendCaseNotice.php` — tenant-reply entry whitelist +
  `EscalationExhausted` (tenant-web revival).
- `app/Policies/RepairCasePolicy.php` — `reply` + `resolve`/`abandon` arms;
  new `setStance` gate.
- `app/Http/Controllers/CaseController.php` — `setStance` action.
- `routes/web.php` — `cases.stance` POST; `members.escalation-routes` GET
  (auth-only, not in public nav).
- `resources/views/cases/show.blade.php` — stance badge; **suppress the
  phantom "Next escalation" date** on an exhausted case (D0.5 ride-along).
- `resources/views/cases/_action_panel.blade.php` — exhausted-state copy,
  signposting link, stance selector.
- `database/seeders/LetterTemplateSeeder.php` — repoint the existing closer
  + tenant-notice rows to D14 (descriptions + solicitor-gating note); no
  new rows, no wording finalised.

---

## Test disposition (462 → 485, +23)

- **+12** `tests/Feature/Phase4/EscalationExhaustedTest.php`: the two
  headlines (transition-on-exhaustion incl. closer with NULL stage + counter
  unchanged; sweep-inert at all three stance values), allow-reply revival on
  **both** edges, stance-is-label-only (sweep + controller), the
  engaged-never-exhausts guard, the closer one-shot across re-exhaustion, and
  the no-active-template safety case.
- **+6** `CaseTransitionMapTest`: 5 new positive edges + the new same-status
  no-op row.
- **+3** `CaseIllegalTransitionTest`: `escalation_exhausted` →
  on_hold / open / self all rejected.
- **+2** the terminal-statuses dataset auto-extends over `CaseStatus::cases()`
  (`resolved`/`abandoned` → `escalation_exhausted` rejected).
- **Reworked, net 0** — `SilenceSweepLiveTest`'s 2b exhaustion test. It
  previously asserted the intent was **log-only / sends nothing** (correct
  for 2b). D14 promotes it to a real transition, so the old assertion is no
  longer true. **Per CLAUDE.md this is a correct behaviour change, not a
  weakening** — the test now asserts *more* (status → `escalation_exhausted`,
  closer + tenant notice queued, `executed=true`). Renamed accordingly.

---

## A decision I made that you should sanity-check

**The closer is one-shot, not fire-every-transition.** Ruling 1 says "fire
it on the transition"; D5's own words say "a **one-shot** closing letter,"
and ruling 2's binding invariant is "no further automatic letters, ever."
The only scenario where once-vs-every differs is a tenant-web revival that
later re-exhausts (rare — most revivals are landlord emails, which flip to
engaged and never re-exhaust). I built **once-per-case** (guarded on a prior
closer message) as the most D5-coherent reading, and a test locks it. If you
want a fresh closer on every re-exhaustion instead, it's a one-line change
(drop the `exhaustionCloserAlreadySent` guard).

---

## Design-doc consolidation (the ruling asked; here's what I did)

D5 stays authoritative. I did **not** create a competing "D14" section.
Instead:
- Added a **"Implementation note — Phase 4 / D14"** subsection *inside* D5
  pinning the details D5 left open (never-engaged-only; closer made real +
  one-shot + NULL-stage; clock-stops reconciliation; both revival edges +
  no window; engagement-flag reconciliation with D15; label-only stance;
  signposting; solicitor-gating). It extends D5, contradicts nothing.
- Resolved the stale **D8 table** row for `escalation_exhausted` (was
  "Deferred to Phase 4") to the D14 reality.
- Noted in the §2 heading that D14 is the Phase-4 build of D5.

No overlap or contradiction remains between D5 and the build.

---

## Acceptance

1. **Suite green vs 462** — **PASS** (485, no weakened assertions).
2. **gafol live-fire** — **pending (your part).** A branch + runbook in the
   2b/3/D15 pattern: drive a never-engaged case to `max_notices` →
   auto-escalate each rung → transition to `escalation_exhausted` + closer
   fires; exhausted case sweep-inert at all three stances (Mailgun: nothing);
   reply revives on both edges; the exhausted notice + signposting render.
   The closer / tenant notice / signposting wording are **solicitor-gated for
   production** — draft is fine for the live-fire.

## Pre-flip obligations carried (additive to D15's)

- **`exhaustion_landlord_closer`** wording — solicitor sign-off (landlord-
  facing legal letter).
- **`tenant_exhaustion_notice`** wording — solicitor sign-off.
- **`members.escalation-routes`** content — solicitor-deferred (stub only;
  no named bodies/thresholds/deadlines).

**Stopping here. No merge until you've run the live-fire and accepted.**

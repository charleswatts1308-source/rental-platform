# CC REPORT — D15 Implementation (engagement-gated escalation)

**Status:** BUILD COMPLETE. Suite green. **Stopped before merge** per
CLAUDE.md — awaiting acceptance (gafol live-fire + your go-ahead).
**Branch:** `d15-engagement-gating` (off `main` `bc743e9`; tag `pre-d15`).
**Commits on branch:**
1. `…` docs: D0 report + brief.
2. `…` docs: D15 added to design doc, supersedes D7.
3. `9ad3a70` feat(d15): engagement-gated escalation (code + tests).

Built strictly to the D0 report as ruled. No scope added.

---

## What changed (file by file)

**Schema**
- `database/migrations/2026_06_13_090000_add_landlord_engaged_to_cases_table.php`
  — `cases.landlord_engaged` boolean, NOT NULL, default false, after
  `ball_with`.
- `app/Models/RepairCase.php` — `landlord_engaged` added to `$fillable`
  and cast `boolean`. **New transition edge** `awaiting_landlord →
  dormant` (event `case_dormant`) — the D15 unauthorised tail. Docblock
  updated.

**The flag (ruling 1 — engaged = ANY token-resolved inbound)**
- `app/Actions/HandleInboundReply.php` — `$case->landlord_engaged = true`
  set in the existing clock-flip block, inside the same transaction,
  persisted by the existing `transitionTo()`/`save()`. Fires on every
  token-resolved inbound **including quarantined** (from-mismatch).
  Idempotent; nothing ever resets it.

**The verdict (D0.2 / ruling 2 — held case stays landlord-ball)**
- `app/Services/Silence/IntendedAction.php` — new case
  `SendAuthorisationNudge`.
- `app/Services/Silence/SilenceClock.php`:
  - `landlordSideVerdict()` — when an escalation is due (clock expired,
    counter < max), branches on `landlord_engaged`. Engaged →
    `heldEscalationVerdict()`; never-engaged → unchanged `SendEscalation`.
  - `heldEscalationVerdict()` — withholds the escalation and walks an
    **authorise-nudge ladder** mirroring the tenant-side ladder, but
    landlord-ball, counting `authorisation_nudge_sent` events, reusing the
    `nudge.*` cadence (no new settings). Unauthorised → `TransitionDormantIntent`
    (ball=Landlord). Counter is **not** incremented (D3 ratchet preserved;
    only the real send ratchets).
  - `authorisationNudgesSentSinceClockStart()` — held-ladder counter.
  - `authorisationPending()` — public derived check (status awaiting_landlord
    + engaged + ball landlord + clock expired + counter < max). **Single
    source of truth** for the page and the policy. No new `CaseStatus`.

**The sweep (D0.5)**
- `app/Console/Commands/SilenceSweep.php`:
  - `shouldExecute` gate: `SendAuthorisationNudge` executes when
    ball=Landlord; `TransitionDormantIntent` execution relaxed to
    ball ∈ {Tenant, Landlord} (transitionTo edge-legality is the real
    guard; only the engaged held branch ever yields it landlord-ball).
  - `executeAuthorisationNudge()` — fires the authorise-nudge mail, writes
    an `authorisation_nudge_sent` event with `nudge_number`/`withheld_notice`.
    **Mail-only — no `case_messages` row, no ball move, clock NOT restarted**
    (same evidential invariant as ordinary nudges). Race-guarded by an
    under-lock recount, mirroring `executeNudge`.
  - `dispatchAuthorisationNudge()` — active-row idiom on
    `authorisation_required_nudge`; `{{notice_number}}` = withheld notice;
    magic-link lands on the case page.
  - Held dormancy reuses `executeDormancyTransition()` (transitions
    awaiting_landlord → dormant via the new edge; dispatches the existing
    `dormancy_transition_notice`).
  - Summary tally gains `send_authorisation_nudge`.

**The authorise action (D0.4)**
- `routes/web.php` — `GET/POST /cases/{slug}/authorise`
  (`cases.escalate.preview` / `cases.escalate.authorise`).
- `app/Http/Controllers/CaseController.php` — injects `SilenceClock`;
  `escalationPreview()` renders the withheld notice + the
  `escalation_authorisation` ui_copy against the existing case (no session
  staging — the D13 difference); `escalationAuthorise()` calls
  `SendCaseNotice::execute()`, which takes its **existing
  `$isAutoEscalation` branch** — the identical send the sweep would have
  auto-fired (counter ratchets, letter frozen in `case_messages`, landlord
  clock restarts). `show()` passes the derived `authorisationPending` flag.
- `app/Policies/RepairCasePolicy.php` — injects `SilenceClock`; new
  `authoriseEscalation` ability gated on ownership +
  `authorisationPending()`.
- `resources/views/cases/authorise.blade.php` — new; mirrors the D13
  preview against the existing case.

**Case page (D0.10 — snag-#15-adjacent, state-aware, not a fix)**
- `resources/views/cases/_action_panel.blade.php` — authorise prompt +
  button when `authorisationPending`.
- `resources/views/cases/show.blade.php` — the "Next escalation" line is
  suppressed when `authorisationPending`, and relabelled "Next notice
  (with your go-ahead)" for engaged cases, so a held case never advertises
  an auto-fire date that won't happen on its own.

**Wording rows (D0.8 — DATA, draft, flagged)**
- `database/seeders/LetterTemplateSeeder.php` — two new rows, both marked
  **DRAFT** in code comments:
  - `authorisation_required_nudge` (`tenant_notification`) — the "landlord
    went quiet — authorise?" nudge. **Charlie's eyes.**
  - `escalation_authorisation` (`ui_copy`) — per-send consent copy on the
    authorise screen. **Charlie's eyes + SOLICITOR PASS** (it is consent to
    send a formal legal letter in the tenant's name).
- **No new `settings` rows** — the authorise-nudge cadence reuses
  `nudge.first_days/second_days/dormancy_days` as recommended in D0.

---

## D0.6 — notify-on-send: CONFIRMED, not rebuilt

`SilenceSweep::executeEscalation()` still calls
`dispatchAutoEscalationTenantNotice()` unconditionally on every auto-send;
the mailable is informational-only (no action), writes no `case_messages`
row, moves no ball, starts no clock. After D15 this path runs **only** for
never-engaged landlords (engaged ones are withheld upstream) — so it
becomes "never-engaged only" by construction, no change. Test
`never-engaged case auto-escalates … and notifies the tenant` pins it.

## D0.7 — backfill: non-issue at pilot (confirmed)

`migrate:fresh` from files; every case created with the flag, flipped by
the inbound handler. Recorded in the migration docblock + design doc D15
that default-false reproduces today's (harmful) behaviour for any
un-backfilled engaged case, derivable from inbound landlord `case_messages`
if ever needed.

---

## Tests (D0.9) — suite 461 passed, 1099 assertions

Baseline was 448; +13 net (new D15 file), and the illegal-transition
disposition is net-zero (one dataset row removed, one positive assertion
added). **No weakened assertions.**

New `tests/Feature/D15/EngagementGatingTest.php` (13):
- flag flips on a genuine inbound; **flips on a quarantined inbound**
  (ruling 1); idempotent / never resets;
- never-engaged → `SendEscalation`; engaged → `SendAuthorisationNudge`;
- never-engaged auto-escalates **and** notifies;
- engaged-then-quiet **withholds** (no `CaseNotice`), fires the
  authorise-nudge, writes **no** escalation `case_messages` row, stays
  awaiting_landlord; clock not restarted;
- tenant authorise fires the held notice: counter ratchets, letter frozen,
  clock restarts;
- authorise preview renders when held; **denied** when not-yet-expired and
  when never-engaged (policy gate);
- unauthorised engaged-quiet → **dormant** via the new edge;
- dormant engaged case **revives** via tenant reply (D11 intact), flag
  stays true;
- **HEADLINE REGRESSION:** a "thanks, all sorted" reply from
  `awaiting_tenant_review` on an engaged case → after landlord silence past
  the interval, the sweep fires **no** escalation letter. The §4 harm is
  closed.

Disposition — `tests/Feature/Phase2/CaseIllegalTransitionTest.php`: the
`awaiting_landlord → dormant` row was asserting the pre-D15 model
("dormancy only from review"). Correct break (CLAUDE.md "tests break
correctly"). Removed from the illegal dataset; legality re-pinned by a new
positive `isTransitionAllowed` assertion in the same file and exercised end
to end by the D15 dormancy test.

---

## Notes / deltas from the D0 plan

- **No new `IntendedAction` for held dormancy.** D0 floated reusing
  `TransitionDormantIntent`; implemented exactly that (gate relaxed),
  avoiding a second new action. One new action total
  (`SendAuthorisationNudge`).
- **Authorise-nudge cadence anchor.** The held branch is entered only at
  silence ≥ interval, so rung 1 fires as soon as the case is held (the
  ≥first-day threshold is already satisfied), rung 2 at `nudge.second_days`,
  dormant at `nudge.dormancy_days` once both have gone out — an explained,
  recoverable sequence (D2), exactly mirroring the tenant-side ladder.
- **No interaction with the D3 counter invariant** — engagement is a
  separate axis; the held verdict never writes a counted row; only tenant
  authorisation (or, for never-engaged, the sweep) fires the real send.

---

## Acceptance status

1. **Suite green vs 448** — ✅ 461 passed (448 + 13; net-zero disposition),
   1099 assertions.
2. **gafol live-fire (landlord-side re-proof)** — ⏳ NOT YET RUN. This
   touches the live 2b escalation engine, so per the brief the landlord-side
   live-fire must be re-run: never-engaged auto-escalates + notifies;
   engaged withholds → nudges tenant → authorises → fires; thank-you reply
   on an engaged case → no escalation. A runbook in the 2b/3 pattern is the
   next artefact once you approve the build.

## Open items needing you

- **Wording sign-off:** `authorisation_required_nudge` (your eyes) and
  `escalation_authorisation` (your eyes **+ solicitor pass**) — both seeded
  as drafts.
- **Go-ahead to write the gafol live-fire runbook** and run the re-proof.

Stopping here before merge.

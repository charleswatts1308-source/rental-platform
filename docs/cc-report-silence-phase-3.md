# Silence Model — Phase 3 implementation report

**Branch:** `silence-phase-3` at `45b4376` (off `main` at `30a2032`).
**Pre-tag:** `pre-silence-phase-3` → `30a2032`.
**Status:** Implementation complete. Merge HELD pending gafol joint
live-fire per acceptance #2.

Implementation discipline: report-first within the phase, no merge
until live-fire passes. Matches Phase 2b precedent.

---

## Acceptance — disposition

| # | Criterion | Status |
|---|---|---|
| 1 | Suite green; count against the post-disposition baseline. | **PASS** — 441 passed (1029 assertions). Net delta −15 vs the 456 post-2b baseline, matching the approved D0.1 disposition (≈37 demolition tests removed, ≈22 new SilencePhase3 tests added). |
| 2 | Live fire on gafol, jointly reviewed. | **HELD** — runbook lands in a follow-up commit; deploy + live-fire is operator-driven; joint review at the end. |
| 3 | Pretend sweep executes nothing, all intents logged. | **PASS** in suite (`pretend mode executes nothing on tenant-side either` in `SweepTenantSideTest`, plus the inherited Phase 2b pretend tests in `SilenceSweepLiveTest`). To be re-verified on gafol. |
| 4 | Diff confirms demolition complete and untouched-list respected. | **PASS** — see §Demolition + §Untouched below. |

---

## Deviations from D0 (none load-bearing)

- **`awaiting_landlord → on_hold` added to the transitions map.** The
  D0.4 reply-policy ruling stated *hold* should be available from
  AwaitingLandlord. The transition wasn't in the pre-Phase-3 map
  (D0 inferred it could be added without flagging). Added in this
  commit; tests assert it both ways.
- **No catch-up dormancy.** D0.5 left this ambiguous — "the dormancy
  notification IS the catch-up communication when the sweep was
  delayed past the nudge thresholds; missed nudges are not back-
  filled." Implemented accordingly: TransitionDormantIntent
  transitions to Dormant + dormancy_transition_notice, no missed
  nudges are dispatched.
- **`tenant_replied` event written on the AwaitingLandlord self-send
  branch** (in addition to the transition path's canonical event).
  Without it the audit trail for a tenant reply when no transition
  happened would be empty (the existing token_issued + token_super-
  seded events are mechanical, not semantic). One explicit event
  write inside `SendCaseNotice::execute` covers it.
- **Compulsory `description` on case create.** D9 said
  cases.description is immutable from creation. The existing form
  already had a `description` field, optional. Phase 3 makes it
  required and removes the "optional unless category requires it"
  logic — the column is NOT NULL, so there is no path that creates
  a case without it.

None of these contradict a D0 ruling. Flagged for completeness.

---

## Demolition — diff-confirmed

Per D0.2 + D0.3 Option A. Concrete removals:

- `app/Console/Commands/SweepDormancy.php` — gone.
- `app/Console/Commands/SweepHolds.php` — gone.
- `routes/console.php` — `cases:sweep-holds`, `cases:sweep-dormancy`
  schedule entries removed; `silence:sweep` keeps its slot with
  `withoutOverlapping`.
- `app/Http/Controllers/CaseController.php` — `sendNext` + `reEngage`
  methods gone.
- `routes/web.php` — `cases.send-next`, `cases.re-engage` gone;
  `cases.reply`, `cases.preview`, `cases.confirm`,
  `magic-link.consume` added.
- `app/Policies/RepairCasePolicy.php` — `sendNext`, `reEngage`
  policy methods gone; `reply` added.
- `resources/views/cases/_action_panel.blade.php` — sendNext +
  reEngage buttons gone; reply form + "raise a new case" panel
  added.
- `app/Mail/Notifications/DormancyReminder.php` — gone.
- `app/Mail/Notifications/HoldExpired.php` — gone.
- `app/Mail/Notifications/LandlordReplyReceived.php` — gone (replaced
  by template-rendered `landlord_reply_received_notice`).
- `resources/views/emails/notifications/dormancy-reminder.blade.php`
  + `hold-expired.blade.php` + `landlord-reply-received.blade.php`
  — gone.
- `app/Enums/CaseStatus.php` — `TenantActionRequired` case dropped.
- `RepairCase::TRANSITIONS` — all rows containing
  `tenant_action_required` removed; new event vocabulary
  (`tenant_replied`) added.
- `RepairCase::applyColumnSideEffects` — TAR-specific
  current_stage++ removed; dormant_at side-effect added.
- `app/Actions/SendCaseNotice.php` — `$isEscalation` branch removed
  along with `escalation_confirmed_by_tenant` event emission;
  `$tenantStatement` parameter removed.
- Test files removed: `tests/Feature/Phase5/SweepDormancyTest.php`,
  `Phase5/SweepHoldsTest.php`, `Phase7/DormancyReminderTest.php`,
  `Phase7/HoldExpiredTest.php`,
  `Phase7/LandlordReplyReceivedTest.php`,
  `SilencePhase2b/TenantClickCoexistsTest.php`.

`Phase7/` directory removed (empty after the deletes).

---

## Untouched — confirmed

Per the brief §Scope explicitly untouched:

- `escalation_exhausted` — Phase 4. Exhausted-intent verdict
  remains intent-only; no transition. Verified by
  `SilenceSweepLiveTest::it logs transition_exhausted_intent and
  sends NOTHING at counter >= max`.
- Admin UI — Phase 5.
- Short case references (snag #4a) — separate pre-flip batch.
- Landlord lookup on create form (#7), delivery webhooks (#8).
- Attachments on tenant replies — out of scope.
- The 2b landlord-side live path — `CaseNotice`, `SendCaseNotice`
  for first-send and auto-escalation, escalation template lookup
  with D1 fallback, ratchet counter via case_messages — all
  unchanged. Verified by `SilenceSweepLiveTest` carried forward
  green.

---

## Test disposition — final

| Group | Count | Notes |
|---|---|---|
| Deleted | ~37 | Whole-file deletes for demolished features (SweepDormancy 11, SweepHolds 8, DormancyReminder 6, HoldExpired 5, LandlordReplyReceived ≈3, TenantClickCoexists 4) |
| Rewritten | ≈50 | TRANSITIONS, SendCaseNotice signature, CaseAction (hold/resolve/abandon valid-state list), CaseCreate (preview→confirm two-step), LetterTemplateRenderer (header wrap), Inbound webhook (no-TAR), CaseTransition* (parametrisation), TurnDetection (no-TAR), ClockStart (container DI), SilenceSweepLive (tenant-side LIVE), etc. |
| Unaffected-but-touched | ≈25 | Fixtures use the factory's new `description` default; cases reference the new transitions; no assertion changes. |
| New under SilencePhase3/ | ≈47 | TenantReplyTest (15), SweepTenantSideTest (11), MagicLinkTest (5), DemolitionTest (16) |

Final: **441 passed, 1029 assertions** on `45b4376`.

---

## Snags closed in this phase

- **#1** (nav title): "Your Rental Profile" → "Your Tenancy" in
  `layouts/app.blade.php`.
- **#3** (dev seed descriptions): closed into D9. `DevLifecycle::SPECS`
  gains a 5th `description` column; `dev:case --description`
  threads through; `dev:letter` filler default removed.
- **#5** (login email pre-fill): superseded by D12 — magic links
  log the tenant in on click; pre-fill no longer relevant.
- **#6** (one-click sign-in): closed by D12 with the same mechanic.
- **#9** (silence_shadow_log truncation): `silence_shadow_log` and
  `magic_login_tokens` added to `DevReset::TABLES`.
- **#10** (sweep summary double-count): collected-row-ID tally in
  `SilenceSweep::handle` replaces the swept_at re-query.
- **#11** (blank issue_description on stage 2+): closed into D9.
  Every send reads `cases.description` (set immutable at creation);
  renderer auto-injects the header block on every render.
- **Half-duplex tenant-reply draft entry**: closed by D8 + the
  `cases.reply` route + UI.

---

## For the gafol live-fire runbook

Notes for whoever writes the Phase 3 runbook (acceptance #2):

- New migrations to run on gafol (in this order):
  - `2026_06_07_080001_add_description_to_cases_table` (NOT NULL —
    pre-flight: confirm `cases` table is empty on gafol).
  - `2026_06_07_080002_add_dormant_at_to_cases_table` (nullable).
  - `2026_06_07_080003_create_magic_login_tokens_table`.
  - `2026_06_07_080004_drop_tenant_action_required_from_cases_status_enum`
    (runs the actual ENUM modify on MariaDB; SQLite-skip is dev-only).
- Re-run `db:seed --class=LetterTemplateSeeder --force` — five new
  template rows (`dormancy_transition_notice`,
  `hold_expired_notice`, `landlord_reply_received_notice`,
  `create_case_authorisation`, plus updates to existing rows that
  stripped HTML wrappers).
- Re-run `db:seed --class=SettingSeeder --force` — two new rows
  (`dormancy.revival_days`, `hold.max_days`).
- Live-fire sequence to exercise the new paths:
  - `dev:lifecycle` (now 7 cases, with description column populated).
  - **Tenant reply round-trip:** open case 3 (awaiting_tenant_review),
    submit a reply via the new UI, verify the landlord receives the
    Mailgun-routed outbound and the case transitions to
    awaiting_landlord; the response from the landlord round-trips
    via the webhook as before.
  - **Tenant nudge live:** `dev:age-clock --case=2 --days=11`
    (case 2 must be in awaiting_tenant_review post-letter +
    landlord-reply) → `silence:sweep` → expect 1 nudge_sent event
    + tenant inbox arrival, NO new case_messages row, clock NOT
    restarted.
  - **Dormancy live:** `dev:age-clock --case=2 --days=31` →
    `silence:sweep` → case 2 transitions to Dormant with dormant_at
    stamped + dormancy_transition_notice mail.
  - **Hold expiry absorbed:** `dev:age-hold --case=5 --days=14`
    (case 5 in OnHold post-pause) → `silence:sweep` → case 5
    transitions direct to AwaitingLandlord with hold_expired event
    + hold_expired_notice mail.
  - **Magic-link sign-in:** open any tenant notification email from
    the above runs; click the link; verify auto-login + landing on
    the case page; second click should redirect to /login with "this
    link has already been used."
  - **Create-case preview:** open a fresh case via the form; verify
    the preview page renders notice 1 + the authorisation copy;
    Edit goes back to the form; Confirm fires the send.
  - **Dormancy revival window:** age a dormant case beyond 90 days
    (manual DB nudge or future setting); verify the case page
    shows the "raise a new case" panel instead of the reply form.
- **Pretend safety:** `silence:sweep --pretend-today=YYYY-MM-DD`
  on a tenant-side aged case → 0 mail queued, executed=false on
  the shadow row, NO case_messages, NO transitionTo.
- **Idempotency:** run the sweep twice in a row after the nudge
  fires; second run must not emit a duplicate nudge_sent event.
- **No double-fire** on a clock-aged case run through the sweep
  twice within the same second — the lockForUpdate race guard's
  status + clock + hold_until comparisons should catch it.

Operator pre-flight: dotrent `SELECT COUNT(*) FROM cases;` should
be zero before the description NOT NULL migration ships. (Gafol
is wiped by `dev:reset`; flagged in D0.6.)

---

## Repo refs at end of phase

- `main` = `30a2032` (unchanged — Phase 3 work lives on the branch).
- `pre-silence-phase-3` tag = `30a2032`.
- `silence-phase-3` branch = `45b4376` (local; not yet pushed).
- Standing rule: no push without explicit user ask. Operator pushes
  the branch when ready to drive the gafol pull + live-fire.

## Open thread for the live-fire review

1. Confirm dotrent cases count is empty before the migration runs
   on production (Phase 3 is pre-flip so this should be true; verify
   explicitly).
2. Confirm Mailgun `auto_escalation_tenant_notice` style template
   reuse for the new tenant notifications is acceptable — they all
   share the same generic mailable shape (pre-rendered subject + body),
   distinguished only by the template row code.
3. Confirm 7-day magic-link expiry is sensible against the actual
   tenant inbox cadence observed on gafol.

When the live-fire passes, the `--no-ff` merge into `main` can land,
followed by the Phase 3 close-out write-up (same shape as
`silence-phase-2b-writeup-Sun-2026-06-07-0602am.md`).

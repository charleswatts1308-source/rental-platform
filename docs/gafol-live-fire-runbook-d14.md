# Gafol Live-Fire Runbook — Phase 4 / D14 (`escalation_exhausted`)

**File:** `docs/gafol-live-fire-runbook-d14.md`
**Branch:** `d14-escalation-exhausted` (head on origin — the D14 build
commit `0336484` or later docs commits).
**Purpose:** acceptance #2 of `docs/cc-brief-d14-phase4.md` (as ruled) —
deploy + live-fire on gafol, jointly reviewed before the `--no-ff` merge.
Acceptance #1 (suite green, 462 → **485**) is already done.
**Pre-flight assumption:** gafol is on the **D15-merged** state (post-merge
of `d15-engagement-gating`). D14 adds two purely-additive migrations
(append `escalation_exhausted` to the status ENUM; add the nullable
`exhausted_stance` column), so — unlike Phase 3 — there is **no
`dev:reset`-before-`migrate` ordering trap**. `dev:lifecycle` resets anyway.

**Seeded knobs that drive the day-math (report them, don't guess):**
`escalation.interval_days = 14`, `escalation.max_notices = 4`.
The exhaustion gate in the verdict is `counter >= max_notices` — it fires
when the counter **reaches 4**, not when a 5th send would exceed it. Case 2
starts at **counter=1** (notice 1 sent at setup, not an expiry), so the
counter at each sweep is the off-by-one to watch:

| Age+sweep | counter at sweep | action | counter after |
|---|---|---|---|
| step 2 | 1 | SEND notice 2 | 2 |
| step 3 | 2 | SEND notice 3 | 3 |
| step 4 | 3 | SEND notice 4 | 4 |
| step 5 | 4 | **TRANSITION** (no send) | 4 |

So **three** expiries SEND (notices 2, 3, 4) and the **4th** expiry
TRANSITIONS into `escalation_exhausted`. Walk in knowing the counter at
each rung — if a sweep reports a counter you don't expect from this table,
stop and recount before proceeding.

**Mail ground truth (carried from D15 live-fire):**
- **Mailgun log is the source of truth**, not the inbox.
- Landlord = the **BT address** (`DEV_LANDLORD_EMAIL`) — delivers clean.
- Tenant = Gmail — sandbox `5.7.1` / DMARC-quarantine on tenant-bound sends
  is **expected**; confirm in the Mailgun log, not the Gmail inbox.
- **Before the run: clear the Gmail inbox + spam** so the few tenant
  notifications that do land are unambiguous.

---

## Deploy (Plesk Laravel Toolkit / Git — operator-driven)

```
1. Plesk → Git (gafol subscription) → Pull updates
   • d14-escalation-exhausted head = 0336484 (or later docs commits).

2. Plesk → Laravel Toolkit → Composer → composer install
   • NOT --no-dev — gafol is preprod and the dev:* commands run the
     live-fire (production/renters.rent uses --no-dev; preprod does not).

3. Plesk → Laravel Toolkit → Artisan → migrate --force
   • Two new migrations land, both additive (no table wipe needed):
     - 2026_06_14_090000_add_escalation_exhausted_to_cases_status_enum
       (real ENUM widening on MariaDB; SQLite-skip is dev-only)
     - 2026_06_14_090100_add_exhausted_stance_to_cases_table
       (nullable string, default NULL)

4. Plesk → Laravel Toolkit → Artisan → db:seed --class=LetterTemplateSeeder --force
   • Idempotent (updateOrCreate by code). Refreshes the two exhaustion
     rows already present since D5: exhaustion_landlord_closer
     (type=exhaustion_landlord) and tenant_exhaustion_notice
     (type=tenant_notification). Confirm BOTH come back active=1.
     No new rows; wording is DRAFT (solicitor-gated for production).
   • SettingSeeder is NOT re-run — D14 adds no settings.

5. Plesk → Laravel Toolkit → Artisan → config:clear ; cache:clear

6. Confirm the scheduler heartbeat (silence:sweep is the only scheduled
   command). The live-fire drives the sweep manually below, so the
   heartbeat isn't blocking — but verify for daily-run confidence.
```

---

## Live-fire sequence (run in order; paste output verbatim)

### Setup

```
1. php artisan dev:lifecycle
   • Resets + reseeds the 7-status spread. The subject is CASE 2
     (Bob Brennan, "Damp patches…") — status awaiting_landlord,
     NEVER ENGAGED (first letter sent, no landlord reply),
     counter = 1 (one outbound system row, stage_at_send=1),
     ball_with=landlord, clock just started.
   • Note case 2's id/url_slug from the summary table for the steps
     below (referred to as "case 2").
```

### Scenario 1 — HEADLINE: drive the full ladder, then exhaust + closer

**THE OVERSHOOT DISCIPLINE (learned on D15 — do not batch the aging).**
`dev:age-clock` subtracts from the CURRENT clock position, and every
escalation sweep RESTARTS the clock. So the rhythm is, for EACH rung:

> **age 15 → read the gauge → sweep → confirm exactly one rung moved.**

The gauge is the last line of `dev:age-clock`:
`silence_days now: 15/14 -> EXPIRED`. If it ever reads more than ~15/14
(e.g. 30/14), you batched — STOP, you've lost a rung boundary. One rung
per age, one escalation per sweep.

```
2. RUNG → notice 2 (counter 1 → 2)
   php artisan dev:age-clock --case=2 --days=15
   • EXPECT gauge: silence_days now: 15/14 -> EXPIRED
   php artisan silence:sweep
   • EXPECT: case 2 verdict send_escalation, executed=true; ONE CaseNotice
     to the BT landlord (notice 2) in Mailgun; AutoEscalationTenantNotice
     to tenant; counter → 2; current_stage → 2; clock restarted to now.

3. RUNG → notice 3 (counter 2 → 3)
   php artisan dev:age-clock --case=2 --days=15
   • EXPECT gauge: 15/14 -> EXPIRED  (fresh, because step 2 restarted it)
   php artisan silence:sweep
   • EXPECT: send_escalation, executed=true; notice 3 to landlord; counter → 3.

4. RUNG → notice 4 (counter 3 → 4) — the LAST escalation letter
   php artisan dev:age-clock --case=2 --days=15
   • EXPECT gauge: 15/14 -> EXPIRED
   php artisan silence:sweep
   • EXPECT: send_escalation, executed=true; notice 4 to landlord; counter → 4.

5. TIPPING RUNG → escalation_exhausted + the closer (counter already 4)
   php artisan dev:age-clock --case=2 --days=15
   • EXPECT gauge: 15/14 -> EXPIRED
   php artisan silence:sweep
   • EXPECT (the headline):
     - verdict = transition_exhausted_intent, executed=true.
     - case 2 status → escalation_exhausted.
     - SendExhaustionCloser fires: Mailgun shows the exhaustion_landlord
       closer to the BT landlord AT THE TRANSITION.
     - tenant_exhaustion_notice queued to the tenant.
     - NO send_escalation this sweep (no notice 5 — there is no rung 5).
   • COUNTER-NOT-INFLATED PROOF: the closer is a System outbound row with
     stage_at_send=NULL, so the ladder counter stays 4. Verify in SQL
     (post-state block: closer row present, counter still 4).
```

### Scenario 2 — sweep-inert at ALL THREE stances

The three-stance coverage IS the point: it proves the cosmetic
`exhausted_stance` never touches the machine. Drive case 2 (now exhausted)
through each value and sweep. Set the stance via the case page UI (this
also exercises the setStance route/policy/controller).

```
6. Stance = UNSET (default after step 5). 
   php artisan silence:sweep
   • EXPECT: case 2 NOT in the swept set at all (excluded at the query
     layer) — no shadow_log row written for case 2 this run; Mailgun shows
     NOTHING; status still escalation_exhausted; counter still 4.

7. Stance = ABANDONED.
   • In the browser as the tenant, open case 2 → "How do you see this
     case?" select → Abandoned → Save. EXPECT: a grey "Abandoned" badge by
     the status; status UNCHANGED (still escalation_exhausted).
   php artisan silence:sweep
   • EXPECT: identical inertness — no shadow row, no mail, no change.

8. Stance = UNRESOLVED.
   • Browser: same select → Unresolved → Save. Badge flips to "Unresolved".
   php artisan silence:sweep
   • EXPECT: identical inertness.

9. Clear the stance back to "Not set" via the same select (optional) and
   confirm the badge disappears. (Pure UI; no sweep needed.)
```

### Scenario 3 — allow-reply revival, BOTH edges (+ live one-shot proof)

This reuses case 2 across both edges and proves the closer one-shot guard
live, in the rare re-exhaustion path.

```
10. EDGE A — TENANT web reply revives to awaiting_landlord.
    • Browser as tenant, case 2 (exhausted): the reply form is shown
      (policy allows it, no window). Submit:
      Body: "Actually this is still not fixed — please re-open."
    • EXPECT after POST /cases/{slug}/reply:
      - case 2 status → awaiting_landlord.
      - landlord_engaged UNCHANGED (still 0) — a tenant reply does not
        engage the landlord.
      - 1 NEW outbound case_messages row, sender_role=tenant,
        stage_at_send=NULL, body_raw carries the D9 header + the text.
      - a fresh reply token minted; clock restarted; ball=landlord.
      - CaseNotice queued to the BT landlord.

11. RE-EXHAUST + ONE-SHOT CLOSER PROOF (the rare edge the guard covers).
    php artisan dev:age-clock --case=2 --days=15
    • EXPECT gauge: 15/14 -> EXPIRED
    php artisan silence:sweep
    • EXPECT:
      - verdict = transition_exhausted_intent, executed=true; case 2 →
        escalation_exhausted again (counter was still 4).
      - NO SECOND CLOSER: Mailgun shows NO new exhaustion_landlord letter
        to the landlord this sweep. The System/NULL-stage closer count on
        the case stays at 1 (post-state SQL). The "this is the final
        notice" letter is not re-sent — it already was the final notice.
      - tenant_exhaustion_notice DOES fire again (informational, mail-only).

12. EDGE B — LANDLORD email reply revives to awaiting_tenant_review.
    php artisan dev:reply --case=2
    • This POSTs a signed inbound to the REAL webhook
      (VerifyMailgunSignature → MailgunInboundController →
      HandleInboundReply), against the active token the closer minted.
    • EXPECT output: http_status=200; case_status=awaiting_tenant_review.
    • EXPECT (the edge that would silently kill allow-reply if missing):
      - the inbound was NOT dropped — case 2 status → awaiting_tenant_review.
      - landlord_engaged FLIPS to 1. The revived case is now D15-gated:
        any future escalation is tenant-authorised, never automatic.
      - ball_with=tenant; clock restarted; landlord_reply_received_notice
        queued to the tenant.
```

### Scenario 4 — notice + signposting + phantom-date

```
13. Tenant exhaustion notice → signposting page.
    • In the Mailgun log, open the tenant_exhaustion_notice fired at
      step 5 (subject: "…the escalation process has run its course").
      Click its "Open this case" magic link → signs in, lands on case 2.
    • On the exhausted case page, the action panel shows the end-of-road
      copy and a "See your options from here" button →
      route('members.escalation-routes').
    • Click it. EXPECT: the stubbed signposting page renders (placeholder
      sections only — no named bodies/thresholds/deadlines; wording is
      solicitor-deferred).

14. Members-wall + not-in-nav checks for the signposting page.
    • While LOGGED OUT, GET /members/escalation-routes directly →
      EXPECT redirect to /login (auth+verified gate).
    • Confirm the page is NOT linked from the public top nav (it's reached
      only from the exhausted case + the notice).

15. Phantom "Next escalation" date is gone.
    • While case 2 is in escalation_exhausted (re-open it; after step 12 it
      is awaiting_tenant_review, so for this check use the state BEFORE
      step 10, or note it from steps 5–9): on the exhausted status card,
      EXPECT NO "Next escalation" date row — the clock has stopped and the
      projected date is suppressed (it would otherwise show a phantom
      future date that never fires).
    • Practically: easiest to eyeball this during steps 6–9 when case 2 is
      sitting in escalation_exhausted with the stance UI visible.
```

---

## Post-state SQL checks (phpMyAdmin → gafol cases DB)

```sql
-- Ladder + exhaustion (scenario 1). Run AFTER step 5, BEFORE step 10.
SELECT id, status, current_stage, ball_with, landlord_engaged, exhausted_stance
FROM cases WHERE id = 2;
-- Expected at step 5: status='escalation_exhausted', current_stage=4,
--   ball_with='landlord', landlord_engaged=0, exhausted_stance NULL.

-- Counter NOT inflated by the closer.
SELECT
  SUM(stage_at_send IS NOT NULL) AS ladder_counter,         -- escalation rungs
  SUM(direction='outbound' AND sender_role='system'
      AND stage_at_send IS NULL) AS closer_rows             -- the closer(s)
FROM case_messages
WHERE case_id = 2 AND direction='outbound' AND sender_role='system';
-- Expected at step 5: ladder_counter=4, closer_rows=1.

SELECT event_type, occurred_at FROM case_events
WHERE case_id = 2 AND event_type IN ('case_exhausted','exhaustion_closer_sent')
ORDER BY id;
-- Expected: one case_exhausted (the transition) + one exhaustion_closer_sent.

-- One-shot closer across re-exhaustion (scenario 3, after step 11).
SELECT SUM(direction='outbound' AND sender_role='system'
           AND stage_at_send IS NULL) AS closer_rows
FROM case_messages WHERE case_id = 2;
-- Expected: STILL 1 — the re-exhaustion did NOT mint a second closer.

SELECT event_type, COUNT(*) FROM case_events
WHERE case_id = 2 AND event_type IN ('case_exhausted','exhaustion_closer_sent')
GROUP BY event_type;
-- Expected: case_exhausted = 2 (step 5 + step 11),
--           exhaustion_closer_sent = 1 (the one-shot closer only).

-- Revival, both edges.
-- After step 10 (tenant web reply): status awaiting_landlord, engaged 0.
-- After step 12 (landlord email): 
SELECT status, ball_with, landlord_engaged FROM cases WHERE id = 2;
-- Expected: status='awaiting_tenant_review', ball_with='tenant',
--           landlord_engaged=1.

-- Sweep-inertness: no shadow rows for case 2 from the inert sweeps.
SELECT swept_at, intended_action, executed FROM silence_shadow_log
WHERE case_id = 2 ORDER BY swept_at DESC LIMIT 12;
-- Expected: the steps 6/7/8/11 sweeps wrote NO row for case 2 while it was
--   escalation_exhausted (query-layer exclusion). The only case-2 rows are
--   the send_escalation rows (steps 2-4) and transition_exhausted_intent
--   (steps 5, 11). None from the inert sweeps.

-- Stance is label-only: setting it changed nothing mechanical.
SELECT exhausted_stance, status, current_stage FROM cases WHERE id = 2;
-- Across steps 6-9 only exhausted_stance changed; status/current_stage
-- were constant.
```

---

## Shadow report inspection

```
16. php artisan silence:shadow-report --include-no-action --limit=50
```

Key rows to spot:
- **Steps 2–4:** case 2 `send_escalation`, `executed=true`, escalation
  counter climbing 1→2→3→4 across the three rows.
- **Step 5:** case 2 `transition_exhausted_intent`, `executed=true`,
  reasoning mentions "ladder exhausted (counter=4 >= max=4)".
- **Step 11:** a SECOND case 2 `transition_exhausted_intent`,
  `executed=true` (the re-exhaustion) — but NO new closer (confirmed in
  SQL, not the shadow log).
- **Steps 6/7/8:** NO case 2 rows — the exhausted case is excluded at the
  query layer, sweep-inert at every stance.

---

## What the joint review verifies (D14 acceptance #2)

- **Headline transition + closer.** A never-engaged case auto-escalated
  rung-by-rung to `max_notices`, then transitioned to
  `escalation_exhausted` AND fired the landlord closer — with the counter
  NOT inflated (closer is stage_at_send=NULL).
- **Sweep-inert at all three stances.** Unset / abandoned / unresolved
  each swept to nothing — no escalation, no nudge, no silence accrual, no
  shadow row, no mail. The cosmetic flag never reaches the machine.
- **Allow-reply revival, both edges.** Tenant web reply →
  `awaiting_landlord` (engaged untouched); landlord email →
  `awaiting_tenant_review` (engaged flips). The webhook did NOT drop the
  inbound exhausted-case reply.
- **Closer is one-shot.** A re-exhaustion (after a tenant-web revival) did
  NOT re-fire the closer.
- **Notice + signposting.** The exhaustion notice rendered and routed the
  tenant (via the case page) to the members-wall signposting stub, which
  is auth-gated and not in public nav.
- **No phantom date.** The exhausted status card shows no projected "Next
  escalation" date.

If all hold, the `--no-ff` merge of `d14-escalation-exhausted` into `main`
can land, followed by the D14 close-out write-up.

---

## Notes for future-me reading this post-clear

- Brief: `docs/cc-brief-d14-phase4.md`. Design doc
  (`docs/llcs-silence-model-design.md`, D5 + its "Implementation note —
  Phase 4 / D14") WINS on any conflict — D5 is authoritative; the build
  honours the four rulings recorded in `docs/cc-report-d14.md`.
- D0 fact-find: `docs/cc-report-d14-d0.md`. Implementation report:
  `docs/cc-report-d14.md` (test reconciliation 462 → 485).
- Procedural difference vs Phase 3's runbook: **no `dev:reset`-before-
  `migrate`** — D14's two migrations are additive. The one discipline that
  matters here is the **rung-by-rung aging** (one `dev:age-clock --days=15`
  → gauge check → one `silence:sweep` per escalation), never batched.
- The closer / `tenant_exhaustion_notice` / `members.escalation-routes`
  wording are **solicitor-gated for production**; DRAFT is fine for this
  live-fire. Production cutover is governed by `docs/pre-flip-checklist.md`.
- Merge stays held until this joint review passes.
```

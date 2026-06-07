# Gafol Live-Fire Runbook — Silence Model Phase 3

**File:** `docs/gafol-live-fire-runbook-3.md`
**Branch:** `silence-phase-3` at `1e29064` (or later — head of the
branch on origin)
**Purpose:** acceptance #2 of `docs/cc-brief-silence-phase-3.md` —
deploy + live-fire on gafol, jointly reviewed before `--no-ff` merge.
**Pre-flight assumption:** gafol is on the 2b cutover (post-merge
state of `silence-phase-2b`). The cases table holds 2b-era rows,
some with `status='tenant_action_required'` — both the
`cases.description` NOT NULL migration and the ENUM-drop migration
will fail until those rows are gone. `dev:reset` clears them.

---

## Deploy (Plesk Laravel Toolkit / Git / FileZilla — operator-driven)

The new pin vs the 2b runbook: **`dev:reset` runs BEFORE `migrate`.**
The cases table state from 2b live-fire would otherwise block two
Phase 3 migrations.

```
1. Plesk → Git (gafol subscription) → Pull updates
   • silence-phase-3 head should land at 1e29064 or later
     (subsequent docs commits don't affect the deploy).

2. Plesk → Laravel Toolkit → Composer → composer install
   • Use `composer install` (NOT --no-dev) — gafol is preprod and
     the dev:* commands run the live-fire. Production (renters.rent)
     uses --no-dev; preprod does not.

3. Plesk → Laravel Toolkit → Artisan → dev:reset --force
   • Wipes the 2b-era case data, including any rows with
     status='tenant_action_required'. Without this step migration
     #4 below fails with "Data truncated for column 'status'" and
     migration #1 has no path forward on a non-empty cases table.
   • Plesk allow-list — runs because gafol is preprod.

4. Plesk → Laravel Toolkit → Artisan → migrate --force
   • Four new migrations land:
     - 2026_06_07_080001_add_description_to_cases_table
       (NOT NULL — relies on step 3's wipe)
     - 2026_06_07_080002_add_dormant_at_to_cases_table
     - 2026_06_07_080003_create_magic_login_tokens_table
     - 2026_06_07_080004_drop_tenant_action_required_from_cases_status_enum
       (actual ENUM drop on MariaDB; SQLite-skip is dev-only)

5. Plesk → Laravel Toolkit → Artisan → db:seed --class=SettingSeeder --force
   • Idempotent. Inserts dormancy.revival_days=90 and
     hold.max_days=60; leaves existing 2a/2b rows in place.

6. Plesk → Laravel Toolkit → Artisan → db:seed --class=LetterTemplateSeeder --force
   • Idempotent. Inserts dormancy_transition_notice,
     hold_expired_notice, landlord_reply_received_notice,
     create_case_authorisation (ui_copy). Updates the existing five
     rows to the new content (HTML wrappers stripped — renderer
     owns them now); flag if any have been hand-tuned on gafol.

7. Plesk → Laravel Toolkit → Artisan → config:clear
   Plesk → Laravel Toolkit → Artisan → cache:clear

8. Confirm scheduler heartbeat is firing on gafol.
   • silence:sweep is the only scheduled command post-Phase-3
     (cases:sweep-holds + cases:sweep-dormancy demolished).
     Without a per-minute schedule:run cron the sweep never fires
     automatically; the live-fire drives it manually below so the
     heartbeat isn't strictly blocking, but verify for the daily-
     run confidence.
```

---

## Live-fire sequence (run in order; paste output verbatim)

```
1. php artisan dev:lifecycle
   • Wipes case data (again — defensive) and reseeds 7 lifecycle
     cases (Option A — no TAR recipe). Description is populated
     per-case from the SPECS row's new 5th column.

2. php artisan dev:age-clock --case=2 --days=11
   • Case 2 is in awaiting_landlord post-letter. We age the clock
     so the landlord-side silence is 11 days; with
     escalation.interval_days=14, the clock is NOT expired yet.
     This is the SETUP for step 3 — we want the landlord-side
     silence path quiet while we exercise tenant-side ladder.

3. We need a tenant-side case for the nudge ladder. Use case 3
   (awaiting_tenant_review post landlord reply — ball=tenant):
   php artisan dev:age-clock --case=3 --days=11
   • Tenant-side silence = 11 days. With nudge.first_days=10,
     case 3 is past the nudge 1 threshold.

4. php artisan silence:sweep
   • REAL sweep. Expected:
     - case 3 verdict = send_nudge with nudge_number=1
       (next unwalked rung; nudge_sent count was 0).
     - case 3 executed = true, NO new case_messages row, clock
       NOT restarted (Correction 1).
     - all other non-terminal cases either no_action or
       no-impact verdicts.

5. php artisan dev:age-clock --case=3 --days=11
   • Tenant-side silence now ~22 days. Past nudge 2 threshold (20).

6. php artisan silence:sweep
   • REAL sweep. Expected:
     - case 3 verdict = send_nudge with nudge_number=2
       (next unwalked rung; count was 1).
     - case 3 executed = true, count → 2 after.
     - clock STILL not restarted.

7. php artisan dev:age-clock --case=3 --days=9
   • Tenant-side silence ~31 days. Past dormancy threshold (30).

8. php artisan silence:sweep
   • REAL sweep. Expected:
     - case 3 verdict = transition_dormant_intent
       (ladder walked: count=2 >= 2 AND silence >= 30).
     - case 3 transitions to status=dormant, dormant_at stamped
       at NOW(), dormancy_transition_notice queued.

9. Gap-scenario probe on a fresh tenant-side case. Use case 4
   (also awaiting_tenant_review post landlord reply):
   php artisan dev:age-clock --case=4 --days=35
   • Tenant-side silence = 35 days, count=0 (no prior nudges).

10. php artisan silence:sweep
    • REAL sweep. Expected the D2 ladder-walk rule to hold:
      - case 4 verdict = send_nudge with nudge_number=1 — NOT
        transition_dormant_intent. The fix asserts the case is
        never dormancy-fied without being warned first.
      - case 4 status remains awaiting_tenant_review, dormant_at
        remains NULL.

11. php artisan silence:sweep
    • REAL sweep. Expected: case 4 fires nudge 2 (count=1, silence
      still 35, threshold for nudge 2 is 20). Still no transition.

12. php artisan silence:sweep
    • REAL sweep. Expected: case 4 transitions to dormant (count=2,
      silence>=30). The gap-scenario takes three sweeps; dormancy
      only lands now.

13. Tenant reply round-trip. Use case 2 (awaiting_landlord).
    Open the case in a browser as the tenant (cases.show), submit
    a reply via the new UI:
    • Body: "Adding details — there is mould patch ~30cm wide
      spreading from the corner."
    • Expected after POST /cases/{slug}/reply:
      - 1 NEW outbound case_messages row with sender_role=tenant,
        stage_at_send=NULL, letter_template_id=NULL, body_raw
        containing the D9 header block + the tenant's verbatim
        text.
      - case 2 status remains awaiting_landlord (self-target;
        already in AwaitingLandlord), 1 tenant_replied case_event,
        silence_clock_started_at restarted at now.
      - CaseNotice mailable queued to landlord.

14. Webhook round-trip. As the landlord, reply to the email
    received in step 13. Expected:
    - HandleInboundReply receives the inbound, writes 1 inbound
      case_messages row, case transitions to awaiting_tenant_review.
    - landlord_reply_received_notice queued to tenant (template-
      rendered, replacing the old blade LandlordReplyReceived).

15. Hold pause + expiry. Use case 1 (open / freshly created via
    dev:lifecycle, walk it forward to AwaitingTenantReview via a
    landlord reply first — OR pick a case already there).
    • Submit the hold form with hold_until = today + 7 days.
      Expected: case transitions to OnHold, hold_until set,
      audit event hold_set.
    • Try to submit hold_until = today + 120 days (beyond default
      hold.max_days=60). Expected: validation error
      ("You can pause this case for up to 60 days."), no
      transition.
    • php artisan dev:age-hold --case=<that case> --days=14
    • php artisan silence:sweep
      Expected: ResumeFromHold verdict, transition to
      awaiting_landlord, hold_expired_notice queued, silence
      clock restarted ball=landlord.

16. Magic-link sign-in. Open any tenant notification email from
    the runs above (auto-escalation, nudge, dormancy, hold
    expired, landlord reply received). Click the "Open this case"
    link.
    • First click: signs the tenant in, lands on cases.show.
    • Second click on the SAME link: redirects to /login with
      flash error "This link has already been used".
    • A token whose expires_at is in the past (force via SQL on
      magic_login_tokens for a chosen row): redirects to /login
      with "This link has expired".

17. Create-case preview (D13). As tenant, GET /cases/create, fill
    the form including a description, POST /cases.
    • Expected redirect to /cases/preview (NO case row created
      yet).
    • Preview page renders the rendered notice 1 against the
      in-memory payload + the create_case_authorisation ui_copy
      content.
    • Edit returns to the form with inputs pre-filled.
    • Confirm POSTs to /cases/preview/confirm: case row created
      with description frozen on cases.description, first send
      fires, redirect to /cases/{slug}.

18. Dormancy revival window panel. Use case 3 or 4 (now dormant
    from steps 8 / 12). Force the dormant_at backwards via SQL:
    UPDATE cases SET dormant_at = NOW() - INTERVAL 100 DAY WHERE
    id = <case>;
    • Open the case in the browser. Expected:
      - The reply form is NOT shown.
      - The "raise a new case" panel is shown with a CTA to
        cases.create.
    • Restore dormant_at (UPDATE ... SET dormant_at = NOW() - INTERVAL
      30 DAY) and re-open: reply form is back.

19. Pretend safety:
    php artisan silence:sweep --pretend-today=2026-07-15
    • Expected: 0 mail queued, all verdicts logged with
      is_pretend=true, executed=false, no transitions, no
      case_messages writes, no nudge_sent events.

20. Idempotency double-run. Immediately after step 4 (the first
    real sweep that fired case 3's nudge 1), run another sweep:
    php artisan silence:sweep
    • Expected: case 3 verdict either no_action (count=1 vs
      threshold; below threshold for nudge 2) or "superseded by
      concurrent sweep — nudge_sent count changed" if the race
      guard fires. Either way: no duplicate nudge_sent event.

21. Idempotency double-run on dormancy. Immediately after step 8
    (the transition to dormant): run another sweep:
    php artisan silence:sweep
    • Expected: case 3 excluded from the sweep at the query layer
      (status=dormant is in the terminal-exclusions list). No row
      written for case 3 in this run's shadow log.
```

---

## Post-state SQL checks

Run these in phpMyAdmin (gafol → cases DB) after the live-fire
sequence completes. The expected values pin the acceptance.

```sql
-- Case 3 (the normal-cadence walk: steps 2-8)
SELECT id, url_slug, status, dormant_at, current_stage,
       silence_clock_started_at, ball_with
FROM cases WHERE id = 3;
-- Expected: status='dormant', dormant_at NOT NULL,
--           silence_clock_started_at = the original ball-flip
--           timestamp (NOT restarted by any nudge), ball_with='tenant'.

SELECT event_type, meta, occurred_at
FROM case_events WHERE case_id = 3 AND event_type = 'nudge_sent'
ORDER BY id;
-- Expected: 2 rows. meta JSON contains nudge_number=1 then =2.

SELECT event_type, occurred_at FROM case_events
WHERE case_id = 3 AND event_type = 'case_dormant';
-- Expected: 1 row. occurred_at = step 8 sweep time.

SELECT COUNT(*) FROM case_messages
WHERE case_id = 3 AND direction = 'outbound'
  AND sender_role = 'system' AND stage_at_send IS NOT NULL;
-- Expected: 1 (the original first-send stage 1; nudges never
--           write case_messages rows — evidential invariant).


-- Case 4 (the gap-scenario walk: steps 9-12)
SELECT id, status, dormant_at FROM cases WHERE id = 4;
-- Expected: status='dormant', dormant_at NOT NULL, set at step 12.

SELECT event_type, meta, occurred_at FROM case_events
WHERE case_id = 4 AND event_type = 'nudge_sent' ORDER BY id;
-- Expected: 2 rows (nudge_number=1, nudge_number=2). Three sweeps
-- ran (steps 10, 11, 12); only the first two fired nudges, the
-- third transitioned to dormant.


-- Case 2 (tenant reply round-trip: steps 13-14)
SELECT status, current_stage, ball_with FROM cases WHERE id = 2;
-- Expected: status='awaiting_tenant_review' (after step 14's
-- webhook), ball_with='tenant'.

SELECT direction, sender_role, stage_at_send, letter_template_id,
       LEFT(body_raw, 200) AS body_preview
FROM case_messages WHERE case_id = 2 ORDER BY id;
-- Expected: 3 rows.
--   1. outbound system, stage_at_send=1 (the first letter)
--   2. outbound tenant, stage_at_send NULL,
--      letter_template_id NULL (the tenant reply from step 13)
--   3. inbound landlord (from step 14)

SELECT event_type FROM case_events WHERE case_id = 2
  AND event_type IN ('tenant_replied', 'inbound_received');
-- Expected: both present.


-- Hold pause + expiry (step 15)
SELECT id, status, hold_until FROM cases WHERE id = <the case>;
-- After dev:age-hold + sweep: status='awaiting_landlord',
-- hold_until is preserved (historical record per the design).

SELECT event_type FROM case_events
WHERE case_id = <the case>
  AND event_type IN ('hold_set', 'hold_expired');
-- Expected: both present.


-- Magic links (step 16)
SELECT purpose, used_at, expires_at FROM magic_login_tokens
WHERE user_id = <tenant user_id> ORDER BY id DESC LIMIT 10;
-- Expected: rows per fired notification (auto_escalation,
-- dormancy_nudge, dormancy_transition, hold_expired,
-- landlord_reply_received). At least one row has used_at NOT NULL
-- (the token consumed in step 16's first click). expires_at is
-- created_at + 7 days for every row.


-- Pretend safety (step 19)
SELECT intended_action, executed, is_pretend, pretend_today,
       COUNT(*)
FROM silence_shadow_log
WHERE pretend_today = '2026-07-15'
GROUP BY intended_action, executed, is_pretend, pretend_today;
-- Expected: rows exist with is_pretend=true, executed=false for
-- every verdict species; ZERO rows with executed=true under that
-- pretend_today value.

SELECT COUNT(*) FROM case_messages
WHERE created_at >= '<step 19 timestamp>';
-- Expected: 0 — pretend wrote no case_messages.


-- Sanity: no nudge_sent events EVER write a case_messages row
SELECT cm.id FROM case_messages cm
JOIN case_events ce ON ce.case_id = cm.case_id
WHERE ce.event_type = 'nudge_sent'
  AND ABS(TIMESTAMPDIFF(SECOND, cm.created_at, ce.occurred_at)) <= 1
  AND cm.sender_role = 'system';
-- Expected: 0 rows. Nudges and outbound system rows are
-- intentionally decoupled.
```

---

## Shadow report inspection

```
22. php artisan silence:shadow-report --include-no-action --limit=50
```

Expected shape: rows from all sweeps, sorted swept_at descending.
Key rows to spot:

- **Steps 4, 6:** case 3 `send_nudge` rows with `executed=true`,
  `nudge_number=1` then `=2`.
- **Step 8:** case 3 `transition_dormant_intent`, `executed=true`,
  reasoning mentions "nudge ladder walked (count=2)".
- **Steps 10, 11:** case 4 `send_nudge` (1 then 2) at silence_days
  ~35; reasoning notes "next unsent nudge".
- **Step 12:** case 4 `transition_dormant_intent`, `executed=true`,
  reasoning naming the ladder-walked count.
- **Step 15 (sweep after age-hold):** the holdee case
  `resume_from_hold`, `executed=true`, reasoning mentions hold_until.
- **Step 19:** every case under `pretend_today=2026-07-15` has its
  verdict logged with `is_pretend=true`, `executed=false`.

---

## What the joint review verifies

For acceptance #2 to pass on Phase 3:

- **Dormancy walks the ladder.** Case 3 received nudge 1 + nudge 2
  before transition_dormant_intent ever fired. Case 4 (gap
  scenario) took three sweeps to land in dormant; the first two
  filled the ladder.
- **No clock restart on nudge.** `silence_clock_started_at` on
  case 3 is the original ball-flip from step 1's dev:lifecycle,
  not any of the post-nudge timestamps.
- **Nudge / notification evidential invariant.** Every `nudge_sent`
  event has NO corresponding `case_messages` row; every tenant
  notification (auto_escalation, dormancy_transition, hold_expired,
  landlord_reply_received) is mail-only.
- **Tenant reply round-trip.** A tenant reply via the new UI
  writes a `sender_role=tenant` outbound case_messages row,
  emits `tenant_replied`, and round-trips a landlord reply back
  via the webhook.
- **Hold pause + expiry absorbed.** Hold form respects
  hold.max_days; sweep transitions OnHold past hold_until direct
  to AwaitingLandlord with a hold_expired audit event.
- **Magic links.** First click signs in + lands on case; second
  click rejected with "already used"; expired link rejected with
  "expired"; neither dead-ends.
- **Create-case preview gates the first send.** POST /cases stages
  to session; POST /cases/preview/confirm materialises the case
  and fires the send.
- **Dormancy revival window panel.** Inside 90 days the reply
  form renders; beyond 90 days the "raise a new case" panel
  renders.
- **Pretend safety.** silence:sweep --pretend-today on a tenant-
  side aged case writes shadow rows with executed=false, queues
  no mail, writes no case_messages, no transitions.
- **Idempotency.** Second sweeps within the same minute don't
  re-fire nudges or re-transition dormant cases.

If all hold, the `--no-ff` merge of `silence-phase-3` into `main`
can land, followed by the Phase 3 close-out write-up.

---

## Notes for future-me reading this post-clear

- The brief is `docs/cc-brief-silence-phase-3.md`. Design doc
  (`docs/llcs-silence-model-design.md` at 30a2032 or later) wins
  if anything conflicts.
- The implementation report is `docs/cc-report-silence-phase-3.md`
  — the test reconciliation table closes 456 → 446.
- Phase 2b's runbook is `docs/gafol-live-fire-runbook-2b.md`. The
  big procedural differences in Phase 3 vs 2b:
  - **`dev:reset` BEFORE `migrate`** (the cases ENUM drop and the
    description NOT NULL migration need the table empty).
  - Two seeders re-run (SettingSeeder + LetterTemplateSeeder).
  - Live-fire walks the ladder over multiple sweeps; one nudge per
    sweep is the rule.
- Production cutover is governed by `docs/pre-flip-checklist.md`.
- Merge stays held until joint review passes per acceptance #2
  in the brief.

# Gafol Live-Fire Runbook — Silence Model Phase 2b

**File:** `docs/gafol-live-fire-runbook-2b.md`
**Branch:** `silence-phase-2b` at `4c7915d` (or later — head of the branch on origin)
**Purpose:** acceptance #2 of `docs/cc-brief-silence-phase-2b.md` — deploy + live-fire on gafol, jointly reviewed before `--no-ff` merge.
**Pre-flight done:** `LetterTemplateSeeder` confirmed idempotent (empirical, two consecutive re-seeds, row count stable). New row `auto_escalation_tenant_notice` ships with this seeder; without re-seeding, the active-row idiom silently skips the tenant notification and the fire fails on a non-bug.

---

## Deploy (Plesk Laravel Toolkit / Git / FileZilla — operator-driven)

```
1. Plesk → Git (gafol subscription) → Pull updates
   • silence-phase-2b head should land at c880883 (or 4c7915d
     if CLAUDE.md commit is not yet on origin at deploy time —
     the live-fire works either way; CLAUDE.md is repo doc only).

2. Plesk → Laravel Toolkit → Composer → composer install
   • Use `composer install` (NOT --no-dev) — gafol is preprod,
     and dev:age-clock / dev:lifecycle are needed for the live-fire.
     Production (renters.rent) uses --no-dev; preprod does not.

3. Plesk → Laravel Toolkit → Artisan → migrate --force
   • Three new migrations land:
     - 2026_06_06_165442_drop_next_stage_eligible_at_from_cases_table
     - 2026_06_06_165443_drop_template_key_from_case_messages_table
     - 2026_06_06_165443_add_executed_to_silence_shadow_log_table

4. Plesk → Laravel Toolkit → Artisan → db:seed --class=LetterTemplateSeeder --force
   • Idempotent. Inserts the new `auto_escalation_tenant_notice` row;
     leaves the four existing rows in place (they get updated to seed
     values — flag this if any have been hand-tuned on gafol).

5. Plesk → Laravel Toolkit → Artisan → config:clear
   Plesk → Laravel Toolkit → Artisan → cache:clear

6. Confirm scheduler heartbeat is firing on gafol.
   • Pre-flip-checklist B1: without a per-minute `schedule:run` cron,
     `silence:sweep` never fires automatically. Today's live-fire
     drives the sweep manually so heartbeat isn't strictly blocking,
     but verify it for the daily-run confidence.
```

---

## Live-fire sequence (run in order; paste output verbatim)

```
1. php artisan dev:lifecycle
   • Wipes preprod case data, reseeds 8 lifecycle cases.

2. php artisan dev:age-clock --case=2 --days=15
   • Ages case 2's silence clock 15 days back, leaving the snapshot
     intact (the D4 in-flight guardrail is preserved under aging).
     With escalation.interval_days = 14, silence_days now reads
     as EXPIRED.

3. php artisan silence:sweep
   • REAL sweep (no --pretend-today). Expected: 6 cases evaluated
     (terminal cases excluded at the query layer), case 2 fires
     send_escalation, executed=1, all others no_action.

4. php artisan silence:sweep
   • Immediate second run. Expected: 6 cases evaluated, send_
     escalation=0, executed=0. Case 2's clock was just restarted
     inside the first sweep's transaction; the lockForUpdate race
     guard sees the fresh clock and skips. NO double-send.

5. php artisan silence:sweep --pretend-today=2026-07-11
   • Pretend horizon ~+35d from today (2026-06-06). Expected on the
     8 lifecycle cases: 0 mail queued (pretend forces full shadow
     on either side), tenant-side verdicts logged as send_nudge or
     transition_dormant_intent, landlord-side as send_escalation
     intents — all with executed=false, is_pretend=true.
```

---

## Post-sweep state check (case 2)

```
6. php artisan tinker --execute="\$case = \App\Models\RepairCase::find(2); echo 'status: '.\$case->status->value.PHP_EOL; echo 'current_stage: '.\$case->current_stage.PHP_EOL; echo 'outbound stage 2 msgs: '.\$case->messages()->where('stage_at_send', 2)->count().PHP_EOL; echo 'auto_escalation_sent events: '.\$case->events()->where('event_type', 'auto_escalation_sent')->count().PHP_EOL;"
```

Expected output:
```
status: awaiting_landlord
current_stage: 2
outbound stage 2 msgs: 1
auto_escalation_sent events: 1
```

- `awaiting_landlord` — auto-escalation sent WITHOUT transitioning (per
  silence-phase-2b D0.4: status stays put, ratchet advances on the row).
- `current_stage: 2` — bumped 1 → 2 by the auto-escalation branch.
- `outbound stage 2 msgs: 1` — exactly one stage-2 letter (no double-send
  from the second sweep).
- `auto_escalation_sent events: 1` — exactly one canonical event for the
  sweep-fired escalation; ruling-b vocabulary distinction in the audit
  trail (vs `notice_sent` + `stage_advanced` for tenant clicks).

---

## Shadow report inspection

```
7. php artisan silence:shadow-report --include-no-action --limit=30
```

Expected shape: rows from the three sweeps (real #3, real #4, pretend
#5), sorted swept_at descending. For case 2:
- Sweep #3 row: `executed=true`, `intended_action=send_escalation`,
  `intended_letter_template_id` → `landlord_wakeup_generic`,
  `escalation_counter_value=1`, reasoning mentions `clock expired (15/14 days)`.
- Sweep #4 row: `executed=false`, reasoning either "clock not expired"
  (post-restart) or "superseded by concurrent sweep" if a race guard
  fired.
- Sweep #5 row (pretend): `executed=false`, `is_pretend=true`,
  `pretend_today=2026-07-11`, verdict reflects the +35d horizon.

---

## What the joint review verifies

For acceptance #2 to pass:

- **Mailgun log** (operator-side check, not in this output): exactly
  one outbound `CaseNotice` to the landlord (the case 2 stage 2
  letter), exactly one outbound `AutoEscalationTenantNotice` to the
  tenant. Both delivered, not bounced.
- **Both Gmail inboxes** (operator-side): landlord inbox has the
  formal `Repair issue notice 2 — <address> (case <slug>)` email;
  tenant inbox has the private "We've sent notice 2 to your landlord"
  notification.
- **Second-sweep race guard** (output #4): executed=0, no second
  outbound row, no second event.
- **Pretend forces shadow** (output #5): zero mail queued, intents
  still logged across all non-terminal cases.
- **State invariants** (output #6): status remains `awaiting_landlord`,
  `current_stage = 2`, exactly one stage-2 outbound, exactly one
  `auto_escalation_sent` event.

If all five hold, the `--no-ff` merge of `silence-phase-2b` into `main`
can land. If any hold partially, the implementation report's
disposition list gets a follow-up entry and we triage.

---

## Notes for future-me reading this post-clear

- The brief is `docs/cc-brief-silence-phase-2b.md`. Design doc wins
  if anything conflicts.
- This runbook is for the gafol live-fire only. Production cutover is
  governed by `docs/pre-flip-checklist.md` (A1 hard gate covers this
  phase).
- Merge stays held until joint review passes per acceptance #2 in
  the brief.

# Dispatch sequence — technical reference

The consolidated map of **which letter template fires, when, and in what
order** across a repair case's life. This sequence exists nowhere as a
single artifact in the design doc (which is organised per-decision,
D1–D16) — it only assembles when you read the sweep code. This doc is
that assembly.

**Derived from** the seeder + the sweep logic — verify against code if
in doubt:
- `database/seeders/LetterTemplateSeeder.php` — the seeded templates
- `app/Services/Silence/SilenceClock.php` — the pure decision logic
- `app/Console/Commands/SilenceSweep.php` — the execution

Default interval keys shown in `[brackets]` (from `SettingSeeder` /
`SilenceClock::snapshotCurrentSettings()`). Companion: the user-facing
`dispatch-sequence-plain-english.md`.

---

## The landlord escalation ladder (the spine)

Ball with the **landlord** (last `case_messages` row was outbound), clock
expired:

| Step | Template `code` | `type` | When |
|---|---|---|---|
| Notice 1 | `landlord_wakeup_generic` | `escalation` | Case creation (`SendCaseNotice`) |
| Notice 2, 3, 4… | `landlord_wakeup_generic` | `escalation` | Silence ≥ `escalation.interval_days` [14] and counter < `escalation.max_notices` [4] |
| Closer (one-shot) | `exhaustion_landlord_closer` | `exhaustion_landlord` | On transition into `escalation_exhausted`, once counter ≥ max [4] |

- The single `landlord_wakeup_generic` row serves notices 1..N via
  `LetterTemplate::forEscalation($n)` — active `stage=N` → else active
  `stage=NULL` fallback. It is the **only** seeded `escalation` row.
- Each landlord notice also fires the tenant heads-up
  `auto_escalation_tenant_notice` (`tenant_notification`).
- The closer also fires `tenant_exhaustion_notice` (`tenant_notification`).
- The closer carries `stage_at_send = NULL`, so it never counts toward
  the ladder, and it is **one-shot** (guarded by
  `exhaustionCloserAlreadySent()`).

## Two branches off the landlord ladder

**A. Landlord never engaged** (`landlord_engaged = false`) → notices
auto-send on the clock straight through to the closer (above).

**B. Landlord engaged then went quiet (D15)** → the next escalation is
**withheld**; an authorise-nudge ladder runs instead. Mail-only, ball
stays with landlord throughout:

| Step | Template `code` | `type` | When |
|---|---|---|---|
| Authorise-nudge 1 | `authorisation_required_nudge` | `tenant_notification` | Silence ≥ interval (held immediately) |
| Authorise-nudge 2 | `authorisation_required_nudge` | `tenant_notification` | `nudge.second_days` [20] |
| Dormant (unauthorised tail) | `dormancy_transition_notice` | `tenant_notification` | Both nudges sent + silence ≥ `nudge.dormancy_days` [30] |
| Tenant authorises | `escalation_authorisation` | `ui_copy` | Tenant action → real notice sends via `SendCaseNotice`, clock restarts |

- The authorise-nudge ladder counts `authorisation_nudge_sent` events
  (distinct from the tenant-side `nudge_sent`), so the two ladders never
  collide.
- The counter is **not** incremented while withheld — the D3 ratchet
  advances only when the tenant authorises and the real send fires.

## The tenant-side nudge ladder

Ball with the **tenant** (last message inbound / case waiting on them):

| Step | Template `code` | `type` | When |
|---|---|---|---|
| Nudge 1 | `tenant_nudge_generic` | `tenant_nudge` | Silence ≥ `nudge.first_days` [10] |
| Nudge 2 | `tenant_nudge_generic` | `tenant_nudge` | Silence ≥ `nudge.second_days` [20] |
| Dormant | `dormancy_transition_notice` | `tenant_notification` | Both nudges sent + silence ≥ `nudge.dormancy_days` [30] |

- The clock is **not** restarted on a nudge — silence keeps accruing
  from the original ball-flip toward dormancy.
- One nudge per sweep; dedup hangs on the count of `nudge_sent` events
  since `silence_clock_started_at`.

## Event-driven (not clock-driven)

| Trigger | Template `code` | `type` |
|---|---|---|
| Landlord replies (inbound webhook → `HandleInboundReply`) | `landlord_reply_received_notice` | `tenant_notification` |
| OnHold case passes `hold_until` (sweep `ResumeFromHold`) | `hold_expired_notice` | `tenant_notification` |
| Create-case preview, before notice 1 | `create_case_authorisation` | `ui_copy` |

## Key invariant — what lands on the evidential record

Only `escalation` and `exhaustion_landlord` templates are
**landlord-facing letters** persisted to `case_messages`. Everything
`tenant_nudge` / `tenant_notification` is **mail-only** and never writes
a `case_messages` row.

This is load-bearing: the escalation counter is **derived** as "outbound
system rows with non-null `stage_at_send`" (`escalationCounter()`).
Persisting a nudge as a `case_messages` row would inflate the counter and
misfire the ladder. (`ui_copy` templates render to a web page, not into
an email — the D9 header block + envelope wrap are skipped.)

## Lookup rules (how a template is chosen)

- `LetterTemplate::forEscalation($stage)` — active `escalation` with
  `stage = N`, else active `escalation` with `stage = NULL`. Null return
  = misconfiguration the caller must handle.
- `LetterTemplate::firstActiveOfType($type)` — first active row of the
  type. Null return = **"do not send"** by design (the optional-
  communication / active-row idiom: present → send, absent → skip
  silently).
- The `active` flag is therefore load-bearing on the sweep. Deactivating
  a template mid-escalation has undesigned in-flight semantics — hence
  the `letter_templates.active` toggle is deliberately not exposed in the
  Phase 5 admin UI (phpMyAdmin-only for now).

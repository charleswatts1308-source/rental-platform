# CC BRIEF — Silence Model, Phase 1: Schema + Templates

**Read first:** `docs/llcs-silence-model-design-Sat-2026-06-06-1130am.md`
— the agreed design this phase implements. This brief covers Phase 1 only.

**Discipline:** report first, edit second. Deliverable 0 (below) before
any code changes.

---

## Goal

Move letter content out of code into data, add the settings table, and
add the template reference to `case_messages`. **Zero behaviour change:**
after this phase, the system sends exactly the letters it sends today,
through exactly the same triggers — the only difference is the letter
bodies are rendered from `letter_templates` rows instead of hardcoded
content. The existing test baseline (377 passed / 856 assertions) must
remain green, extended but never weakened.

The old ladder/trigger logic is **not** touched in this phase. That is
Phase 2a/2b.

---

## Git

- Tag current main as `pre-silence-model` before starting.
- Work on branch `silence-phase-1` off main.
- No direct commits to main during the silence-model rewrite.
- Merge will be `--no-ff` after review + green suite + exploratory pass.

---

## Deliverable 0 — Report (before any edits)

1. **Verify and report:** does `case_messages` currently store the full
   rendered body of outbound letters at send time? Quote the relevant
   column(s) and the code path that writes them. If anything less than
   the complete sent text is stored, propose the fix (it lands in this
   phase — evidence must be frozen at send time).
2. Report where letter content currently lives in code (files,
   classes/views) and the call path from trigger to send, so we agree on
   the seam before you cut it.
3. Report any place that would conflict with the schema changes below.

Stop after Deliverable 0 and wait for go-ahead.

---

## Schema changes

### New table: `letter_templates`

| Column | Type | Notes |
|---|---|---|
| id | bigint PK | house convention |
| code | string, unique | machine-readable handle, e.g. `landlord_wakeup_generic` |
| description | string | human note |
| subject | string | placeholder-capable |
| body | text | placeholder-capable |
| type | string | `escalation`, `tenant_nudge`, `exhaustion_landlord`, `tenant_notification` (open set — plain string column, no enum, per repair_categories precedent) |
| stage | tinyint nullable | NULL = generic fallback. Non-NULL only meaningful for `escalation` type |
| active | boolean, default true | |
| timestamps | | |

### New table: `settings`

| Column | Type |
|---|---|
| id | bigint PK |
| key | string, unique |
| value | string |
| timestamps | |

### `case_messages` — add columns

- `letter_template_id` — FK nullable (inbound messages and future
  free-text tenant replies have none)
- `letter_template_updated_at` — datetime nullable, snapshot of the
  template row's `updated_at` at send time ("which wording was in force")

---

## Seeders

### Letter templates

Seed generic wake-ups and notifications, **not** four per-stage letters
(per D1/D3 simplification — the four-letter ladder is retired):

1. `landlord_wakeup_generic` — type `escalation`, stage NULL. Adapt the
   current stage-1 letter content into a stage-agnostic wake-up using
   `{{notice_number}}`.
2. `tenant_nudge_generic` — type `tenant_nudge`, stage NULL. New content;
   keep it plain and supportive — draft something sensible, it will be
   tuned via phpMyAdmin (wording is data, not worth review cycles).
3. `exhaustion_landlord_closer` — type `exhaustion_landlord`, active.
   Sober one-shot ("this matter is now being pursued through external
   channels") — draft, will be tuned.
4. `tenant_exhaustion_notice` — type `tenant_notification`. Draft.

Templates 2–4 are seeded now but **not wired to anything yet** — their
send-points arrive in Phases 2 and 4.

### Settings

| key | value |
|---|---|
| `escalation.interval_days` | 14 |
| `escalation.max_notices` | 4 |
| `nudge.first_days` | 10 |
| `nudge.second_days` | 20 |
| `nudge.dormancy_days` | 30 |

Settings are seeded but **not read by any logic yet** — consumers arrive
in Phase 2a.

---

## Renderer

A placeholder renderer over a **fixed whitelist** of variables. Simple
string substitution of `{{variable}}` tokens.

- **Explicitly not Blade** and not any engine capable of executing code.
  Template bodies are data edited via phpMyAdmin; they must never be an
  execution vector.
- Whitelist (initial): `{{tenant_name}}`, `{{landlord_name}}`,
  `{{case_reference}}`, `{{property_address}}`, `{{issue_description}}`,
  `{{deadline_date}}`, `{{response_days}}`, `{{notice_number}}`. Unknown
  tokens in a template render as-is (visible, greppable) rather than
  silently vanishing — a misspelled token should be obvious in a test
  send.
- **Fallback lookup rule** (implement now, exercised fully in Phase 2):
  for an escalation send at notice number N, select the active
  `escalation` template with `stage = N`; if none, the active
  `stage = NULL` row. Other types: active row by type (and code where
  needed).

## Wiring (the only behaviour-touching change)

The current outbound letter send path switches from hardcoded content to:
look up template → render with placeholders → send → store rendered
subject+body in `case_messages` as today, now also stamping
`letter_template_id` + `letter_template_updated_at`.

Triggers, state transitions, scheduling, Mailgun mechanics: **unchanged**.

---

## Tests

- Existing baseline stays green. Assertions that pin exact letter wording
  may need updating to match seeded template content — flag each such
  change in the report; do not weaken what they assert.
- New coverage: renderer (substitution, whitelist behaviour, unknown-token
  passthrough), fallback lookup rule (stage hit, NULL fallback, inactive
  rows skipped), send path stamps template id + updated_at snapshot,
  rendered body stored verbatim.

## Acceptance

1. Suite green, count ≥ current baseline.
2. `dev:lifecycle` runs clean on local; stage-1 letters render from the
   seeded `landlord_wakeup_generic` template.
3. Diff contains no changes to scheduler/sweep or state-machine logic.
4. Migration runs cleanly on a fresh DB (18 → 20 tables).

## Out of scope (do not touch)

- Old ladder/trigger/sweep logic (Phase 2a/2b)
- Tenant reply UI (Phase 3)
- `escalation_exhausted` state (Phase 4)
- Admin CRUD for templates/settings
- Any reading of `settings` rows by runtime logic

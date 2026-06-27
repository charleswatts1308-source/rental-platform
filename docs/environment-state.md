# Environment state — deployment ledger

Human-readable mirror of what is deployed where. The DB `migrations`
table is the source of truth; this file is reconciled against
`php artisan migrate:status` at each deploy (CLAUDE.md "Deployment ledger").

**Reconcile status:** git tips verified (27 Jun 2026). **gafol** at
`main` (Phase A green) and **dotrent** at `main` (Phase B green) — both
reconciled 27 Jun 2026. Staging-at-or-ahead holds. Remaining: the DNS
flip (Phase C) + prod retirement.

---

## main (working line)
- `origin/main`: `859827b` (pushed 27 Jun 2026). Tags: `post-d16-phase5`
  = `cf2f5c9`.
- Local `main`: ahead by the gafol-naming + this Phase-A-green ledger
  commit (push held per the Git rule until asked).

## gafol — permanent staging (gafol.rent) — ✅ AT MAIN (Phase A green)
- Box: gafol.rent is the staging domain. DB `ukrenter_gafol_db` on
  mysql01. (The stale "ukrenters.rent / HUK" label was wrong —
  ukrenters.rent was a separate earlier site scheduled for deletion.)
- Now at: **`main` / `859827b`** — deployed via Plesk Git pull + composer
  install. Plesk repo `laravel_093fde` tracks the `main` branch.
- Migrations: the D14/D15 set was already Ran; the **2 D16 admin
  migrations ran clean** this session —
  `2026_06_21_100000_create_letter_text_change_history_table`,
  `2026_06_21_100100_create_settings_change_hist_table`.
- Schema verified against MariaDB (Migrations rule): both new tables
  checked via `information_schema` — `created_at` is `datetime NOT NULL`
  with **BLANK extra** on both (NO implicit `ON UPDATE CURRENT_TIMESTAMP`)
  → **#18-clear**. Columns match D16 intent (A1 version-history shape;
  B3 settings-audit shape).
- Surfaces validated loading against live MariaDB:
  - **A** (template editor) — renders full template inventory,
    version-tracking framing present.
  - **B** (settings editor) — 7 settings load, B2 `apply_inflight` flag
    present, default **No**.
  - **C** (case oversight) — read-only, renders the 8 D14 live-fire cases
    across the full state spread (awaiting tenant review, abandoned,
    dormant, on hold, resolved, open).
- Last verified: 27 Jun 2026.

## dotrent — production candidate (dotrent.net) — ✅ AT MAIN (Phase B green)
- Box: dotrent.net, the production candidate for the renters.rent flip.
  DB `ukrenter_dotrent_db` on mysql01. Deploy mechanism: **Plesk Laravel
  Toolkit** (no `.git` in docroot).
- Now at: **`main` / `859827b`** — code via Toolkit, then
  `migrate:fresh --force` (clean rebuild from files, per the June ruling
  — NOT incremental).
- Migrations: **all 35 Ran, batch 1** (fresh build). The full silence
  model + D14/D15/D16 are on dotrent for the first time.
- Schema verified (Migrations rule): `cases` and `case_messages` both
  checked via `information_schema` — every timestamp column has **blank
  extra**, NO implicit `ON UPDATE CURRENT_TIMESTAMP`. **#18-clean.**
- Seed: `RepairCategorySeeder` (11) + `LetterTemplateSeeder` (11) +
  `SettingSeeder` (8, `apply_inflight=0`) run **explicitly by class**.
  NO `DatabaseSeeder`, NO Faker Test User, **empty cases table** — clean
  production-candidate shape.
- Admin: `admin@renters.rent`, id 1, `is_admin=1`, `email_verified_at`
  set; created with verification handled (the gafol `markEmailAsVerified`
  trap avoided). **Future-rebuild note:** admin = the `is_admin` flag,
  set manually post-create; the old "ID 13" rule is retired/stale.
- **Registration — REQUIRED `.env` keys (set in Plesk; a rebuild must
  re-add them — code defaults to false so a rebuild fails SAFE-CLOSED,
  but the allowlist would be EMPTY until re-added):**
  - `REGISTRATION_OPEN_TO_ALL=false` — the front door stays locked; this
    flipping to `true` (+ `php artisan config:cache`) is the single
    go-live switch.
  - `REGISTRATION_ALLOWLIST=<family emails, comma-separated>` — who may
    register during the locked beta. Empty = nobody can register.
  - After editing either: `php artisan config:cache` (prod caches config).
- Surfaces validated against production MariaDB: **A** (11 templates),
  **B** (settings, B2=No), **C** (empty, clean).
- Last verified: 27 Jun 2026.
- Next: Phase C — DNS flip renters.rent → dotrent, Mailgun inbound route
  update, one live round-trip, then record prod retirement.

## prod — renters.rent (Windows, EOL)
- Git tip: UNKNOWN (not reconciled this session). Demo mode; still
  serving live renters.rent until the DNS flip.
- DB: `rentals` + old `file_attachments` still present until the
  `2026_05_24` drop migration runs (per project memory).
- **Retirement trigger:** when the DNS flip completes and prod is
  confirmed dark, record the flip as prod's LAST event here
  ("retired, DNS flipped to dotrent, <date>") and THEN strike this
  entry. The retirement is recorded before removal — so we can prove
  when the old box stopped being authoritative — and the ledger then
  shrinks to three (gafol, dotrent, main) rather than carrying a
  zombie fourth entry.

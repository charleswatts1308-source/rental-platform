# Environment state — deployment ledger

Human-readable mirror of what is deployed where. The DB `migrations`
table is the source of truth; this file is reconciled against
`php artisan migrate:status` at each deploy (CLAUDE.md "Deployment ledger").

**Reconcile status:** git tips verified (27 Jun 2026). **gafol DB
reconciled and at `main`** (Phase A green, 27 Jun 2026). **dotrent DB
reconciled** via `migrate:status` (pre-silence, stops at
`2026_05_24_160000`) — promotion pending (Phase B).

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

## dotrent — flip target for renters.rent
- Git tip / code: `2722ba4` (**pre-silence-model**), deployed via Plesk
  Laravel Toolkit (no `.git` in docroot).
- DB: **pre-silence**, last migration `2026_05_24_160000`. The silence
  model is NOT present. (Reconciled via `migrate:status`, 27 Jun 2026.)
- May 2026 live-fire proved the **Mailgun round-trip only**, on this
  pre-silence schema — not the silence model.
- Behind `main` by: 59 commits.
- **Promotion method: `migrate:fresh` from main's migration files**
  (June ruling: fresh from files, NOT a DB copy). WIPES the DB — safe,
  pre-silence test data only. See `dotrent-deploy-plan.md` Phase B.

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

# Environment state — deployment ledger

Human-readable mirror of what is deployed where. The DB `migrations`
table is the source of truth; this file is reconciled against
`php artisan migrate:status` at each deploy (CLAUDE.md "Deployment ledger").

**Reconcile status:** git tips verified (this session, 27 Jun 2026).
**dotrent DB reconciled** this session via `migrate:status` (pre-silence,
stops at `2026_05_24_160000`). **gafol DB** still UNRECONCILED on
migrations — runs at Phase A of the deploy plan.

---

## main (working line)
- Local `main`: `db095df` (docs: User Guides). 1 commit ahead of origin.
- `origin/main`: `6cb0d9e`. Tags: `post-d16-phase5` = `cf2f5c9`.

## gafol — permanent staging (gafol.rent)
- Git tip: `df3b48f` — **D14-complete, pre-D16** (no Phase 5 admin surfaces).
- DB: at **D14/D15** — missing only the 2 D16 admin tables
  (`letter_text_change_history`, `settings_change_hist`).
- Behind `main` by: 17 commits; schema-wise just those 2 migrations.
- DB migration set: UNRECONCILED — run `migrate:status` at Phase A.
- Plesk Git repo `laravel_093fde` tracks the **`main`** branch (verified
  27 Jun 2026); deploy via Plesk Pull/Deploy, migrations run separately
  via Laravel Toolkit (`migrate --force`).

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

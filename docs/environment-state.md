# Environment state — deployment ledger

Human-readable mirror of what is deployed where. The DB `migrations`
table is the source of truth; this file is reconciled against
`php artisan migrate:status` at each deploy (CLAUDE.md "Deployment ledger").

**Reconcile status:** git tips below are verified (this session, 27 Jun
2026). DB migration sets are UNRECONCILED — no `migrate:status` seen yet.

---

## main (working line)
- Local `main`: `db095df` (docs: User Guides). 1 commit ahead of origin.
- `origin/main`: `6cb0d9e`. Tags: `post-d16-phase5` = `cf2f5c9`.

## gafol — permanent staging (ukrenters.rent / HUK)
- Git tip: `df3b48f` — **D14-complete, pre-D16** (no Phase 5 admin surfaces).
- Behind `main` by: 17 commits; schema-wise only 2 migrations
  (`letter_text_change_history`, `settings_change_hist`).
- DB migration set: UNRECONCILED — run `migrate:status` at next deploy.

## dotrent — flip target for renters.rent
- Git tip: `2722ba4` — **pre-silence-model** (entire silence/email model absent).
- Behind `main` by: 59 commits; 17 migrations (6 CREATE, 11 ALTER on
  `cases`/`case_messages` — #18 ON UPDATE risk applies).
- DB migration set: UNRECONCILED. Pre-deploy checks outstanding:
  migrate:status clean (no half-applied silence migrations),
  `SHOW CREATE TABLE cases / case_messages` for manual drift +
  stray ON UPDATE, `rentals`/old `file_attachments` state.

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

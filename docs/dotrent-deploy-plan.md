# dotrent deploy plan — current main onto dotrent, then DNS flip

Goal: get current `main` onto dotrent, flip renters.rent DNS to it,
begin the family trial. Sequenced so staging (gafol) meets the Phase 5
migrations against MariaDB *before* dotrent does, and so dotrent's real
pending-migration set drives the run — not a git-diff assumption.

Authority: this plan sits UNDER `llcs-silence-model-design.md` and the
CLAUDE.md working agreements. The **Deployment ledger** rule governs the
finish line — every long-lived deploy updates `docs/environment-state.md`
as its last step, reconciled against `php artisan migrate:status`.

State at draft (verified 27 Jun 2026):
- `main` = `3a75a90`, **2 commits ahead of `origin/main`** (`6cb0d9e`):
  the User-Guides docs (`db095df`) + this ledger commit (`3a75a90`).
- gafol = `df3b48f` (D14-complete, pre-D16).
- dotrent = `2722ba4` (pre-silence-model).
- All on one linear history — fast-forwardable, no divergence.

**Prep (before Phase A):** push `origin` so it carries current `main`
(2 commits). Per the Git rule, HOLD the push until Charlie asks. Nothing
deploys from a local-only tip.

---

## STEP 0 — Hard gate + first ledger reconciliation

No migration runs anywhere until this is done and written back. This is
also the ledger's first reconciliation: it clears the UNRECONCILED rows
in `environment-state.md`.

1. **gafol:** `php artisan migrate:status` — confirm it's at the D14/
   pre-D16 set; the only thing pending against `main` should be the 2
   D16 tables.
2. **dotrent:** `php artisan migrate:status` — **RESOLVED this session.**
   dotrent's DB stops at `2026_05_24_160000` (pre-silence). The silence
   model was never deployed here; the May 2026 live-fire proved the
   Mailgun plumbing on this pre-silence schema, not the silence machine.
   This makes dotrent's promotion a clean `migrate:fresh` rebuild from
   main's migration files (June ruling: *fresh from files, NOT a DB copy*)
   — see Phase B. The branch-prediction below is therefore settled.
3. **dotrent:** `SHOW CREATE TABLE cases / case_messages` drift check —
   **no longer a pre-deploy gate.** `migrate:fresh` discards dotrent's
   existing schema entirely, so any drift from the May testing is wiped,
   not migrated over. The #18 verification moves to a SINGLE post-fresh
   `SHOW CREATE TABLE` in Phase B (the rebuilt tables are what matter).
4. **dotrent:** `rentals` / old `file_attachments` — **handled by the
   rebuild.** `migrate:fresh` runs main's full migration set, which
   includes `2026_05_24_160000` (drop `rentals` + recreate
   `file_attachments` standalone). After fresh, `rentals` is gone and
   `file_attachments` is the new standalone table — no manual action.
5. **Write findings back into `docs/environment-state.md`** — replace the
   UNRECONCILED rows for gafol and dotrent with the actual migrate:status
   set. (dotrent's row is reconciled below as of this session; gafol's
   `migrate:status` is still to run at Phase A.)

### STEP 0 branches — SETTLED (dotrent is pre-silence)

This was an open question when the plan was drafted: would dotrent's
status show an incremental delta (the "17" git-diff count was never a
confirmed *pending* count). **It is now settled.** dotrent's DB stops at
`2026_05_24_160000` — pre-silence, the silence model wholly absent. There
is no partial silence schema to reconcile and no incremental run to size.

The promotion method is therefore **`migrate:fresh` from main's migration
files** — rebuild the schema clean in one pass — *not* an incremental run
of any subset of the 17. The earlier branch logic (only-D15/D16 / full-
range / jumbled→STOP) assumed an incremental path and no longer applies.

---

## PHASE A — gafol → main (staging first)

Bring permanent staging at-or-ahead so Phase 5 meets MariaDB on gafol
before dotrent.

1. Deploy current `main` to gafol (fast-forward — gafol is a clean
   ancestor).
2. Run the pending migrations — expected: the **2 D16 tables**
   (`letter_text_change_history`, `settings_change_hist`), both built
   clear of #18.
3. Validate the Phase 5 admin surfaces against MariaDB on gafol:
   - Surface A template editor (version history writes to
     `letter_text_change_history`),
   - Surface B settings editor (audit writes to `settings_change_hist`;
     `escalation.apply_inflight` flag present and **Off**),
   - Surface C read-only case oversight.
4. **Ledger:** record gafol's deploy in `environment-state.md` — tag/
   commit (`main`), date, the 2 migrations applied, what was verified.

Gate to Phase B: gafol green, ledger updated.

---

## PHASE B — dotrent → main (clean rebuild via migrate:fresh)

dotrent is pre-silence (Step 0). Its promotion is a **clean schema
rebuild from main's migration files**, NOT an incremental run of the
17-migration range. Per the June ruling: *migrate:fresh from files, NOT
a DB copy.*

⚠️ **`migrate:fresh` WIPES dotrent's database.** This is intended and
safe: dotrent holds pre-silence *test* data only — no real users, no
real cases. The May live-fire proved Mailgun plumbing, nothing that
needs preserving. Call this out so nobody runs it surprised: the DB is
dropped and rebuilt empty, then reseeded.

1. **Deploy main's code to dotrent** via the **Plesk Laravel Toolkit**,
   NOT `git pull` — there is no `.git` in the docroot. Get main's files
   (incl. all migration files + seeders) onto the box.
2. **`php artisan migrate:fresh --force`** — drops every table and
   rebuilds the schema clean from main's migration files in one pass.
   Because it's a fresh build, the final `cases` / `case_messages`
   tables are created directly (no sequence of ALTERs over a legacy
   shape).
3. **Reseed** — `db:seed --force` (LetterTemplateSeeder, SettingSeeder,
   and any other production-required seeders) so the rebuilt DB has its
   templates + settings.
4. **#18 verification — a SINGLE post-fresh check.** `SHOW CREATE TABLE
   cases;` and `SHOW CREATE TABLE case_messages;` on the rebuilt schema,
   confirming plain `datetime` with no trailing `ON UPDATE`, and
   indexes/FKs/defaults/types as intended. This replaces the 11 per-ALTER
   checks — a fresh build creates the final tables in one pass, so one
   verification of the result suffices. Green SQLite tests prove logic,
   not MariaDB schema (CLAUDE.md Migrations rule).
5. Smoke-check the install on dotrent: app boots, admin surfaces load,
   a case can be created (the create-case preview/confirm flow).
6. **Ledger:** record dotrent's deploy in `environment-state.md` — commit
   (`main`), date, "migrate:fresh from files" as the method, and what was
   verified (the post-fresh `SHOW CREATE TABLE` result).

Gate to Phase C: dotrent green, ledger updated. NOTE: Mailgun on dotrent
(preprod) is `mg.renters.rent`, both directions (CLAUDE.md Mail rule) —
the round-trip is already proven here, so Phase C's check is a
"flip didn't break it", not a first run.

---

## PHASE C — DNS flip + prod retirement

1. Flip DNS: renters.rent → the dotrent install. (Windows renters.rent
   is EOL.)
2. Update the Mailgun **inbound route** to
   `https://renters.rent/webhooks/mailgun/inbound`. This is the one edit
   a flip can leave stale — outbound keeps working regardless, so a
   missed inbound route only surfaces when a landlord reply silently
   fails to land. Do not skip it.
3. Confirm **one inbound round-trip** on the live renters.rent route
   post-flip — the "flip didn't break it" check.
4. **Front door stays LOCKED for the trial.** dotrent runs with
   `REGISTRATION_OPEN_TO_ALL=false` + `REGISTRATION_ALLOWLIST=<family
   emails>` (private-beta gate, merged to main this session). Only
   allowlisted family register; a stranger cannot create an account or
   trigger any outbound letter. See the dotrent ledger entry for the two
   required `.env` keys.
5. Begin the family trial: use Surface B to set short intervals for
   observable pacing; keep the B2 "Applies to In-flight cases" flag
   **Off** so shortened intervals apply cleanly to new cases. gafol stays
   permanent staging. (Solicitor wording sign-off does NOT gate the
   family trial — Charlie's call, 21 Jun; it gates a wider/public launch.
   See `pre-flip-checklist.md`.)
6. **Go-live switch (the ONLY one):** when ready to open beyond the
   family, set `REGISTRATION_OPEN_TO_ALL=true` in dotrent's `.env` +
   `php artisan config:cache`. No other config change. (Public launch
   still gated by the solicitor wording sign-off per `pre-flip-checklist`.)
7. **Ledger — prod retirement:** once the flip completes and prod is
   confirmed dark, record the flip as prod's LAST event in
   `environment-state.md` ("retired, DNS flipped to dotrent, <date>"),
   then strike the prod entry. The ledger shrinks to three (gafol,
   dotrent, main); the retirement is recorded before removal.

---

## Summary of gates

- **Step 0** — dotrent SETTLED (pre-silence → migrate:fresh). gafol's
  `migrate:status` still runs at Phase A.
- **Phase A** before Phase B — gafol takes the 2 D16 migrations first.
- **dotrent = migrate:fresh from files** (clean rebuild, WIPES the DB —
  safe, pre-silence test data only), then reseed, then a **single
  post-fresh `SHOW CREATE TABLE`** #18 check — not 11 per-ALTER checks.
- Every phase ends by **writing the ledger** — a deploy isn't done until
  `environment-state.md` says it happened.

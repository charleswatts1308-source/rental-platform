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
2. **dotrent:** `php artisan migrate:status` — the decisive read. This
   determines Phase B's path (see branches below). Do NOT assume 17.
3. **dotrent:** `SHOW CREATE TABLE cases;` and
   `SHOW CREATE TABLE case_messages;` — check for:
   - manual columns/indexes hand-added during the Mailgun round-trip
     testing (drift), and
   - any stray `ON UPDATE CURRENT_TIMESTAMP` on `updated_at` (#18).
4. **dotrent:** confirm `rentals` / old `file_attachments` state — these
   still exist on the old boxes until the `2026_05_24` drop migration,
   which is OUTSIDE this deploy range (predates `2722ba4`). Note their
   state; do not action them here.
5. **Write findings back into `docs/environment-state.md`** — replace the
   UNRECONCILED rows for gafol and dotrent with the actual migrate:status
   set + the drift/`#18` findings. This is the ledger doing its job.

### STEP 0 branches — dotrent's migrate:status decides Phase B

The "17" elsewhere is a **git-diff count**, not a confirmed pending
count. dotrent ran a D14 live-fire, so its DB may already carry most of
the silence range. The real number is whatever migrate:status reports:

- **Branch 1 — only D15/D16 pending** (live-fire DB ran the rest):
  small clean delta. **Likely**, given the live-fire history. Phase B
  runs just those.
- **Branch 2 — full silence range pending** (DB was reset since
  live-fire): the 17-migration path. Phase B runs the lot, with the #18
  check carrying its full weight (11 ALTERs on `cases`/`case_messages`).
- **Branch 3 — jumbled / partial** (some ran, some didn't, order
  unclear): **STOP.** Do not run anything. Reconcile dotrent's DB to a
  known state first, then re-enter Step 0. A half-applied silence schema
  is the worst case and must not be migrated over.

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

## PHASE B — dotrent → main

Run ONLY the genuinely-pending migrations identified in Step 0 — the
branch result, not the git-diff "17".

1. **#18 dev-MariaDB check first.** For every ALTER in the pending set
   (the `cases` / `case_messages` add-column, drop-column, and status-
   enum migrations), migrate against **dev MariaDB**, `SHOW CREATE TABLE`
   to confirm plain `datetime` with no trailing `ON UPDATE`, indexes/FKs/
   defaults/types as intended, then rollback clean. CREATE TABLE
   migrations get the same confirmation. Green SQLite tests prove logic,
   not schema (CLAUDE.md Migrations rule).
2. Deploy current `main` to dotrent (fast-forward — clean ancestor).
3. Run the pending migrations (`migrate --force` via the Plesk Laravel
   Toolkit). Set is whatever Step 0 / Phase B-branch confirmed.
4. Smoke-check the install on dotrent: app boots, admin surfaces load,
   a case can be created (the create-case preview/confirm flow).
5. **Ledger:** record dotrent's deploy in `environment-state.md` — commit
   (`main`), date, migration set actually applied, what was verified.

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
4. Begin the family trial: use Surface B to set short intervals for
   observable pacing; keep the B2 "Applies to In-flight cases" flag
   **Off** so shortened intervals apply cleanly to new cases. gafol stays
   permanent staging. (Solicitor wording sign-off does NOT gate the
   family trial — Charlie's call, 21 Jun; it gates a wider/public launch.
   See `pre-flip-checklist.md`.)
5. **Ledger — prod retirement:** once the flip completes and prod is
   confirmed dark, record the flip as prod's LAST event in
   `environment-state.md` ("retired, DNS flipped to dotrent, <date>"),
   then strike the prod entry. The ledger shrinks to three (gafol,
   dotrent, main); the retirement is recorded before removal.

---

## Summary of gates

- **Step 0** must come back clean/branchable (Branch 1 or 2) — Branch 3
  STOPS the deploy.
- **Phase A** before Phase B — gafol takes the Phase 5 migrations first.
- **#18 dev-MariaDB check** before any ALTER runs on dotrent.
- Every phase ends by **writing the ledger** — a deploy isn't done until
  `environment-state.md` says it happened.

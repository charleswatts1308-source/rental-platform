# Silence Model — Phase 3 close-out

**Written:** Sunday 2026-06-07, ~16:56 local.
**Status:** CLOSED. Merged to main, gafol joint live-fire passed
(twice — initial run plus a re-verification after the F1/Q1 fixes),
re-verified green on a `migrate:fresh` rebuild.

This is an immutable session write-up. Phase 3's living artefacts
(brief, runbook, design doc, snagging list, implementation report)
keep their stable names in `docs/`; this file is the dated record of
the close-out itself.

---

## Merge

- **Branch:** `silence-phase-3` (off `main` at `30a2032`).
- **Pre-merge tag on main:** `pre-silence-phase-3` → `30a2032`.
- **Merge commit on main:** `8928d8a` — *Merge silence-phase-3 into
  main — tenant-side silence go-live.* `--no-ff`.
- **Diff stat (pre-tag → merge):** 71 files, +4058 / −1865.
- **Suite at merge:** 448 passed, 1050 assertions (post-merge run on
  main, not just the branch).

Phase 3 is the **tenant-side go-live**: the tenant reply UI (D8), the
nudge/dormancy ladder executing for real, magic-link sign-in on every
tenant-touched email (D12), the two-step create-case preview→confirm
(D13), hold expiry absorbed into `silence:sweep`, and the demolition
of `SweepDormancy` / `SweepHolds` / the `tenant_action_required`
state. Full enumeration in `docs/cc-report-silence-phase-3.md`.

## Acceptance criteria — disposition

Brief (`docs/cc-brief-silence-phase-3.md`) lists four:

1. **Suite green** — PASS. 448 passed (1050 assertions). Net delta
   from the 446 implementation-report baseline is +2: the two new
   `CaseCreateTest` cases added for the F1 fix (Edit round-trip +
   cross-tenant draft isolation). Per-file reconciliation through 446
   is in the implementation report; the +2 is itemised under F1 below.
2. **Live fire on gafol, jointly reviewed** — PASS (this doc covers
   the joint review, including the re-verification pass).
3. **Pretend sweep executes nothing, all intents logged** — PASS on
   gafol (see §Live-fire results).
4. **Diff confirms demolition complete and untouched-list respected**
   — PASS (implementation report §Demolition + §Untouched; the merge
   diff lines up against the approved D0.2/D0.3 Option A enumeration).

## Live-fire results — gafol, 2026-06-07

Run per `docs/gafol-live-fire-runbook-3.md`. Operator drove the
sequence in the Plesk Laravel Toolkit; outputs, SQL post-state checks,
and Mailgun/Gmail observations pasted into the joint-review chat.

All acceptance #2 items verified end to end:

- **Tenant reply round-trip.** Reply via the new UI wrote a
  `sender_role=tenant` outbound `case_messages` row (stage_at_send
  NULL, letter_template_id NULL), emitted `tenant_replied`, restarted
  the silence clock ball→landlord, queued `CaseNotice` to the
  landlord. *Inbound* leg simulated via `dev:reply` — the Mailgun
  sandbox cannot route inbound (per CLAUDE.md mail policy). The real
  inbound round-trip re-verifies on dotrent (recorded pre-flip
  condition, below).
- **Nudge ladder walked, including the gap-scenario proof.** Normal
  cadence: nudge 1 → nudge 2 → dormant across three sweeps. Gap case:
  33 days of silence + 1 prior nudge fired **nudge 2, not** a dormancy
  transition — the deviation-2 fix (dormancy walks the ladder; the
  verdict is the next unwalked rung, never the threshold reached) held
  under live fire. This was the headline thing acceptance #2 had to
  prove.
- **Dormancy + `dormant_at`** stamped at transition time; the
  `dormancy_transition_notice` queued.
- **Revival window** verified both sides of 90 days: inside 90d the
  reply form renders; beyond 90d the "raise a new case" panel renders.
- **Hold pause + 60d cap + sweep release.** Hold form respected
  `hold.max_days=60` (120-day attempt rejected with the validation
  message); `dev:age-hold` + sweep released OnHold → AwaitingLandlord
  with `hold_expired` event + notice.
- **Magic links.** First click consumed + signed in + landed on the
  case; second click on the same link rejected ("already used");
  expired token rejected ("expired").
- **Create-case preview + authorisation + delivered notice 1.**
  Preview rendered notice 1 + the `create_case_authorisation` ui_copy;
  Confirm fired the first send; notice 1 delivered to a real BT inbox.
- **D9 header on every mail** — issue description present on every
  outbound render.
- **Pretend executes nothing** — `silence:sweep --pretend-today` wrote
  shadow rows with `executed=false`, queued no mail, wrote no
  `case_messages`, made no transitions.
- **Idempotency clean** at every step — second same-minute sweeps
  re-fired no nudges and re-transitioned no dormant cases.

## One blocker + two questions raised at joint review

### F1 — create-case preview "Edit" cleared all form input (FIXED)

Contradicted approved D0.8 ("Edit goes back to the create form
pre-filled, old() works because the controller flashes the inputs").

Root cause: `store()` redirected to the preview without flashing
input, and the preview's Edit button is a plain GET to `cases.create`,
which rendered an empty form — so `old()` was empty on every field.
The staged data was sitting in the session payload the whole time; the
form just never read it.

Fix (`b2db42e`):

- `create()` now `flashInput()`s the staged payload when one exists
  for the current user. `flashInput()` `put()`s `_old_input`
  immediately, so `old()` resolves it in the same render — the Edit
  button stays a plain GET, no new route.
- A green cue under the photo input tells the tenant their staged
  photos are saved (a file input can't be re-seeded from `old()`).
- **Collateral fix found while tracing it:** `store()` was stowing the
  validated `UploadedFile` objects in the session (`validated[
  'photos']`). It only survived because `confirm()` `forget()`s the
  key before the session serialises, and the test suite uses the array
  session driver. On a real file/db/redis driver (production) that
  serialize would 500. `confirm()` reads the staged `photos` metadata,
  never `validated['photos']`, so `store()` now drops them.
- Tests: +2 in `Phase6/CaseCreateTest.php` (Edit round-trip refills
  the form; a second tenant's draft does not leak). Accounts for the
  446 → 448 delta.

**Re-verified on gafol after `migrate:fresh` + reseed:** the Edit
round-trip retained all form input. ✓

### Q1 — magic-link `expires_at` rewritten to `used_at + 1h` (FIXED)

On the first live-fire, a consumed token (minted with a 7-day expiry)
showed `expires_at` rewritten to roughly `used_at + 1 hour`.

`MagicLinkController::consume()` touches **only** `used_at`; the model
has no mutators. The rewrite was MariaDB applying the implicit
`ON UPDATE CURRENT_TIMESTAMP` that the **first** `TIMESTAMP` column in
a table receives, when it is NOT NULL with no explicit default, under
`explicit_defaults_for_timestamp=OFF` (MariaDB's default on
gafol/prod). The conspicuous "+1 hour" is the timezone gap: `used_at`
is written by Carbon `now()` in the app TZ (UTC) while the engine's
`ON UPDATE CURRENT_TIMESTAMP` fires in the DB session TZ (BST, UTC+1
in June). Invisible to the suite — SQLite has no such semantics.

Fix (`b2db42e`): migration `…080003` now declares `expires_at` as
`dateTime()`, which never receives the implicit `ON UPDATE` clause.

Because gafol's `migrations` table already recorded `080003` as run, a
plain `migrate` would not rebuild the column — the re-verify path
(documented in the runbook addendum) is `migrate:fresh --force` +
both seeders (seed-data box, no real data to preserve).

**Re-verified on gafol after `migrate:fresh` + reseed:** a consumed
token (`used_at` stamped 15:48) retained `expires_at = created_at +
7 days`. The `dateTime()` fix holds on MariaDB. ✓

Audit (snag #18): every `CREATE TABLE` migration was swept for the
same trap. Four other tables carry it — `page_views.view_date_time`,
`error_logs.error_date`, `file_attachments.uploaded_date`,
`silence_shadow_log.swept_at` — all currently insert-only, so the
trap is dormant. Left as-is by ruling (fix only `magic_login_tokens`
this commit); recorded so a future UPDATE path on any of them does not
silently corrupt the column. Verified safe: `reply_tokens` (issued_at
is `useCurrent`; expires_at is nullable and not first) and the
cases/case_events/case_messages/contact_messages tables (first
timestamp is nullable or `useCurrent`).

### Q2 — do create-case photos attach to the notice-1 email? (ANSWERED — no fix)

**Designed behaviour: photos are attached to the outbound notice-1
email, AND recorded on the case.** They are not case-record-only. The
path is end to end:

- `confirm()` → `promotePreviewPhotos()` moves staged files to
  `cases/{id}/…` on the `local` disk and returns the attachment-input
  array.
- `SendCaseNotice::execute(..., attachmentInputs)` writes a
  `message_attachments` row per file against the outbound message.
- `CaseNotice::attachments()` maps `$message->attachments` to
  `Attachment::fromStorageDisk('local', $path)->as(original_filename)
  ->withMime(...)` — i.e. they are dispatched as real MIME attachments
  on the landlord email.

So the live-fire observation (a tenant-attached image not visible at
the landlord inbox) is **not** explained by the design — the design
attaches it. That points to either a transport/inbox artefact (the
gafol run used the Mailgun **sandbox** domain, whose reputation also
caused the Gmail 5.7.1 blocks below) or a possible defect on the real
environment. It is **not** closed as expected behaviour. It becomes a
recorded pre-flip verification: confirm a create-case photo arrives as
an attachment at the landlord inbox over **production** Mailgun
(`mg.renters.rent`), alongside the dotrent inbound re-verification.

NB this question is about **create-case** attachments (which exist and
are wired). Attachments on **tenant replies** are a separate,
deliberately out-of-scope feature — see snag #19.

## Cosmetic / minor snags logged at live-fire (deferred, not blocking)

Raised in the joint-review chat, committed to
`docs/llcs-snagging-list.txt`:

- **#12** — `dev:lifecycle` SPECS descriptions misaligned with their
  row categories (seed-data realism only).
- **#13** — `dev:age-clock` gauge prints the escalation interval for
  tenant-ball cases instead of the nudge/dormancy thresholds (operator
  display only; sweep logic correct).
- **#14** — stale `hold_until` still displayed (and not nulled) after a
  sweep releases a hold.
- **#15** — "Next escalation" line shown while `on_hold` (same
  state-awareness gap as #14).
- **#16** — "Stage 1 of 4" hardcodes the denominator instead of
  reading `escalation.max_notices`.
- **#17** — `dev:reset` crashes when its truncation list names a
  not-yet-migrated table; needs a `Schema::hasTable` guard. Hit during
  today's deploy precisely because Phase 3 inverts the order
  (`dev:reset` BEFORE `migrate`).
- **#18** — the implicit-`ON UPDATE` timestamp-trap audit (see Q1).
- **#19** — attachments on tenant replies: a **feature request** (not a
  defect), promoted by real usage during the live-fire; deferred,
  phase TBD. Explicitly out of Phase 3 scope.

## External record (not a Phase 3 defect)

**Gmail 5.7.1 blocked 4 sandbox sends** during today's live-fire. The
Mailgun log was the only ground truth — the platform showed no trace,
a second independent confirmation of the sent-but-not-delivered blind
spot. Recorded against snag **#8** (delivery webhooks); strengthens the
case for consuming Mailgun delivery/failure events before go-live.
Sandbox-domain reputation, not a Phase 3 issue — production's
DMARC-aligned `mg.renters.rent` is the real-world path.

## Recorded pre-flip conditions (carried to dotrent / production)

These are NOT Phase 3 blockers; they are conditions to verify at the
production cutover, governed by `docs/pre-flip-checklist.md`:

1. **dotrent inbound re-verification.** The tenant-reply *inbound* leg
   was simulated via `dev:reply` on gafol (the sandbox cannot route
   inbound). The real landlord→tenant inbound round-trip must be
   verified on dotrent, where Mailgun inbound (`mg.renters.rent`) is
   live both directions.
2. **Create-case photo arrives as a landlord-inbox attachment over
   production Mailgun** (Q2): designed to attach; verify it does on the
   real domain, given the sandbox left it unseen on gafol.
3. **`cases` count empty before the description-NOT-NULL migration on
   dotrent** (no backfill path this phase; the migration needs an empty
   table — Phase 3 is pre-flip so this should hold; verify explicitly).

## Open thread for Phase 4+

Out of Phase 3 scope, unchanged:

- **Phase 4 — `escalation_exhausted`.** The exhausted-intent verdict
  remains intent-only (no transition); the live path is Phase 4.
- **Phase 5 — admin UI.**
- **Snag #8 — delivery webhooks** (now twice-confirmed; post-cutover
  feature).
- **Snag #4 — short case references** (separate pre-flip batch).
- **Snag #7 — landlord lookup on the create form.**
- **Snag #19 — attachments on tenant replies** (new; phase TBD).

## Session bookkeeping

- Suite at merge: 448 passed, 1050 assertions (green on main).
- Repo refs after this session:
  - `main = 8928d8a` (the Phase 3 merge commit).
  - `pre-silence-phase-3` tag → `30a2032`.
  - `silence-phase-3` branch tip → `5e1028d` (merged via `--no-ff`;
    retained, not deleted, pending operator confirmation).
- Pushed to origin per explicit user ask at close-out.

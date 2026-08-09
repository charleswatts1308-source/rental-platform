# Attachment policy — implementation report

Branch: `feature/attachment-policy` (tag `pre-attachments` on main)
Date: 2026-08-09
Design authority: `docs/attachment-policy-design.md`
Status: **complete, awaiting acceptance before `--no-ff` merge**

Tests: **570 passing** (was 550 at branch point). No weakened assertions.

---

## 1. What shipped

Build steps 1–9 of the design note, in full.

| | before | after |
|---|---|---|
| Letter 1 attachments | 6, hardcoded | admin-configurable ceiling 0–3, **default 1** |
| Chasing letters (stages 2–4) | none, incidentally | **none, deliberately** (R1) |
| Per-file limit | 2MB, hardcoded | 4MB constant; UI advertises `min(4MB, PHP's upload_max_filesize)` |
| Combined-size rule | absent, and needed | **not needed** — 3 × 4MB is safe by construction |
| Error message | "The photos.0 field must not be greater than 2048 kilobytes" | names the tenant's file, states the limit in MB |
| Files listed | case page only | create form, preview, and case page — one shared formatter |

**Ceiling semantics (R2), the load-bearing rule:** the ceiling binds at
**staging**, never at send. It can neither cause an attachment the tenant
did not choose, nor silently remove one they did. `confirm()` deliberately
does not re-check. Pinned by a test that stages under a ceiling of 1, drops
the setting to 0, confirms, and asserts the photo still sends.

**No migration touches a table.** The one migration is a data-only insert of
the settings row, so the CLAUDE.md #18 manual MariaDB check does not apply
to this branch. Verified: `DB::table('settings')->insert(...)`, no
`Schema::` call.

## 2. Deviations from the plan

None in scope. Two additions, both forced by findings:

- The plan's step 2 said `confirm()` should not re-check the ceiling. While
  implementing it, Charlie identified that R2 as originally written only
  guarded one direction. The ruling was amended in the design note before
  the code was written, not after.
- Steps 3 and 4 grew a shared `App\Support\FileSize` helper (plus a unit
  test) rather than formatting inline in three places. The design note
  already required the three views not to drift; a helper is how that is
  enforced rather than hoped for.

## 3. Defects found DURING the build

Four, none of which were in the plan. Three were pre-existing; one was mine.

**#45 — attachment rows written for files that no longer exist.**
`promotePreviewPhotos` guarded the file *move* with `exists()` but pushed
the row regardless. A draft staged one day and confirmed the next — after
`SilenceSweep::cleanupPreviewPhotos` deletes preview folders at 24h — wrote
`MessageAttachment` rows pointing at deleted paths. `CaseNotice` would then
throw inside a queued job, and the case page would list evidence that was
never sent. Found by reading, fixed, logged.

**#46 — the Edit round-trip silently dropped staged photos.** The worst of
them, and pre-existing. `store()` re-staged unconditionally from the
request; a browser cannot re-seed a file input, so the second POST of an
Edit round-trip legitimately carries no files, and the payload's photos were
overwritten with `[]`. Attach a photo, preview, spot a typo, Edit, fix a
word, resubmit — **the letter went without the photo**, while the form said
"your photo is saved, you don't need to re-attach it".

Two failures compounding: the data loss, and a cue asserting the opposite.
Worth recording why it survived: the existing test `assertSee('photo is
saved')` passed throughout. It pinned the **cue**, not the behaviour the cue
claimed. A green test can be testing the lie. That assertion is now
`assertSee('damp.jpg')` — strictly stronger, because the form names the file
rather than counting it.

**A duplicate-error slip (mine).** The first fix for stale validation
messages added a block beside the input but left photo errors in the
page-top summary too, so the message rendered twice and the script cleared
only one copy. Charlie reported the symptom unchanged. Photo errors are now
excluded from the summary entirely.

**Oversize input costing the whole selection.** Reported in the walk: three
files chosen, one too large, all three lost. Server validation failed and
the redirect discarded the selection. Now refused client-side at selection
time — named, not attached, and the good files stay put.

## 4. Test disposition

**Added: 20** (11 feature, 9 unit).

Feature: ceiling enforced and honoured when raised; ceiling 0 hides the
input; **R2 mid-flight ceiling drop**; error copy names the file and says
MB; error shown once and not in the summary; effective limit advertised;
preview lists staged photos; preview states "No photos attached"; #44
abandon-then-new-case; #45 swept file; #46 in three parts (survives,
explicit remove clears, new files replace).

Unit (`tests/Unit/FileSizeTest.php`): KB/MB boundary, the never-render-0-KB
case, negative input, ini shorthand parsing, and **0-means-unknown** — an
unparseable ini value must fall back to our cap, not refuse every upload.

**Changed: 6, none weakened.**

- 3 × `SettingsEditorTest` — one shared payload helper gained the new
  required field. Mechanical.
- 1 × Edit round-trip — assertion **strengthened** from a vague cue to the
  filename, as above.
- 2 × multi-photo tests (`CaseCreateTest`, `OutboundAttachmentTest`) — these
  now call `allowPhotoCeiling(3)` and still assert 2 and 3 attachments
  respectively. Dropping them to a single file would have satisfied the
  suite while deleting the coverage; raising the ceiling keeps the original
  assertion intact and makes the dependency explicit.

## 5. Coverage gaps — read this before trusting the green suite

Three things this suite cannot reach. All were proven by hand instead.

1. **The JavaScript.** The create-form list, accumulation across browses,
   removal, and the client-side size refusal are all browser-side. #43
   (a file input replacing its whole `FileList` on each selection) is
   invisible server-side — `store()` receives one file and validates it
   happily. **No test will ever catch that regressing.**
2. **PHP's real upload limits.** The suite fabricates `UploadedFile` objects
   in memory; they never touch `upload_max_filesize`. Green tests could not
   have found snag #40 and cannot find its like.
3. **The >2MB path locally.** This machine's PHP is `upload_max_filesize=2M`
   / `post_max_size=8M` — the *old* prod values. Prod is now 8M/16M. So the
   2–4MB band is only exercisable on prod.

Walked by hand on 2026-08-09 and confirmed by Charlie: sequential
multi-browse selection; oversize refused at selection with the rest kept;
send through to Mailpit with the attachment present; case-page MB units;
ceiling 0 wording; #44 abandon; #46 Edit round-trip retaining the file.

## 6. Deployment notes

- `php artisan migrate` — one data-only migration, inserts
  `attachments.first_notice_max` if absent. Safe to re-run; touches nothing
  else. **Do not** run `SettingSeeder` on a long-lived environment to get
  this row: it uses `updateOrCreate` and would reset every other tuned
  value.
- Ships at **ceiling 1**. Raise via `/admin/settings` when wanted; no deploy
  required, which is the entire point of the key.
- No schema change, so no MariaDB `SHOW CREATE TABLE` gate (#18).
- Local `.env`/php.ini worth aligning with prod's 8M so local stops
  disagreeing with production about what a valid photo is.

## 7. Explicitly NOT done

- **Resize-on-upload (R7)** — needs a `MessageAttachment` migration for the
  retained original alongside the sent derivative, and therefore the #18
  MariaDB check. Its own branch. Keeping it out is what let this one merge
  on a green suite alone.
- **Attachments on tenant replies (#19)** — with it, `attachments.tenant_reply_max`
  (R5: the key ships with the feature, never before it).
- **Snag #7** — a known landlord email still silently discards the typed
  name. Untouched; the new hint under the Name field is worded so it stays
  true either way.

## 8. Snag list movements

Closed: **#41** (PHP Selector, verified), **#45**, **#46**.
Fixed within: **#39**, **#43**, **#44**.
Partly closed: **#40** — (b) closed by headroom, (a) superseded by this
design.
Corrected: **#44**'s claim that orphaned preview folders are never swept —
`SilenceSweep::cleanupPreviewPhotos` does clean them at 24h; it is the
session key that outlives them.

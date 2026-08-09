# Attachment policy — design note (D0, report only)

Status: **DRAFT for Charlie's mark-up. No code written.**
Raised 2026-08-09, out of the #40 / #41 upload investigation.

Supersedes the narrow "raise `max:2048`, add a combined-size rule" fix
sketched in snag #40, and absorbs snags #39, #43 and #44. Gives snag #19
(attachments on tenant replies) its configuration hook.

The authoritative design doc (`llcs-silence-model-design.md`) says only
this about attachments, at line 682: *"Attachments on tenant replies —
later."* Nothing here conflicts with it; this note fills that gap and
adds the letter-1 and sweep-letter cases it never addressed.

---

## 1. Why now

Two things collided on 2026-08-02:

- A 3.22MB photo — an ordinary phone-photo size — was refused on prod
  with "The photos.0 failed to upload" (#40).
- Chasing that revealed the Plesk PHP Settings panel was not reaching the
  running PHP at all (#41), since resolved: the domain uses CloudLinux
  **PHP Selector**, not Plesk PHP Settings.

With the server limits now genuinely raised (`upload_max_filesize` 8M,
`post_max_size` 16M, both verified from phpinfo Local Values on
2026-08-09), the remaining limits are ours. That makes this the moment to
decide the whole attachment picture rather than patch a number.

## 2. What exists today (verified 2026-08-09)

**Attachments are per-message, not per-case.** `MessageAttachment` rows
hang off a `case_messages` row. `CaseNotice::attachments()`
(`app/Mail/CaseNotice.php:92`) dispatches `$this->message->attachments`,
so each letter carries exactly what was recorded against it — frozen
alongside `body_raw` and `subject`, consistent with the evidential
invariant in CLAUDE.md.

**Only one caller ever supplies attachments.** `SendCaseNotice::execute`
loops `$attachmentInputs` at `app/Actions/SendCaseNotice.php:120`. The
create-case path is the only caller that passes any. The auto-escalation
branch and the tenant-reply branch both call through empty.

Therefore, today:

| letter | stage | attachments |
|---|---|---|
| Letter 1 | `stage_at_send = 1` | up to 6, from the create form |
| Chasing letters | `stage_at_send = 2..4` | none, ever |
| Tenant replies | `stage_at_send = null` | none (feature unbuilt, #19) |

**Limits are hardcoded** at `app/Http/Controllers/CaseController.php:359`:
`'photos' => ['nullable','array','max:6']` and
`'photos.*' => ['file','mimes:jpg,jpeg,png,pdf','max:2048']`.

**An admin settings surface already exists** — D16 Surface B,
`app/Http/Controllers/Admin/SettingController.php`. A spec array drives
the form, every change appends a `settings_change_hist` row, and there is
no create/delete. Adding keys is cheap. One wrinkle: the `int` type
validates `min:1`, so a new range type is needed for a 0–3 value.

## 3. The model

Ruled by Charlie, 2026-08-09:

| letter | attachments | quantity |
|---|---|---|
| Letter 1 (stage 1) | tenant attaches at create | configurable ceiling, 0–3 |
| Chasing letters (stages 2–4) | **never** | not configurable — a fixed property |
| Tenant replies | tenant attaches at reply | configurable ceiling, 0–3 |

The rule, stated once: **a letter carries attachments only when a person
chose them for that letter.** Sweep letters have no author present, so
they carry none. That is not a limitation to be worked around later; it
is the correct answer, and it should be recorded as a decision so nobody
"fixes" it in six months.

### Rulings

**R1 — Sweep letters never attach.** Rejected during design: re-attaching
letter 1's photos to each chasing letter. It would mean the platform
choosing what evidence goes to a landlord in the tenant's name.
Consequence: today's behaviour becomes deliberate, and `SendCaseNotice`
needs no change at all.

**R2 — The admin setting is a ceiling, not a behaviour.** It can only
restrict what a tenant may choose; it can never cause an attachment to be
sent that the tenant did not select. Setting the letter-1 ceiling to 0
removes the file input entirely.

**R3 — Settings read live, not snapshotted.** Deliberately the opposite
of `escalation.interval_days`, which is snapshotted per case under D4 so
in-flight cases keep what they started with. The entire point of these
keys is reacting to a deliverability problem *now*, across cases already
running. Named here as a divergence so it is not read as an oversight.

**R4 — Never retroactive.** A ceiling change affects future sends only.
Letters already sent are frozen and their `MessageAttachment` rows are
untouched, always.

**R5 — The tenant-reply ceiling ships with #19, not before.** An admin
knob that displays a value it does not control is the #41 failure mode in
miniature. Until reply attachments exist, the key does not appear on the
settings page.

**R6 — Per-file size stays a constant, not a setting.** Proposed value
**4MB** (`max:4096`). Quantity is the deliverability lever that was
actually reasoned about; size is bounded by the server and a configurable
value could promise more than the box accepts. One knob that works beats
two where the second can lie.

### Why 3 × 4MB fits

| ceiling | value | headroom at 3 × 4MB |
|---|---|---|
| PHP `upload_max_filesize` | 8M per file | comfortable |
| PHP `post_max_size` | 16M whole POST | 12MB raw — ~4MB spare |
| Mailgun message | 25MB after base64 | ~16.4MB encoded |
| Recipient provider (Gmail, Exchange) | typically 25MB | same |

Because 3 × 4MB is safe by construction, **no combined-size rule is
needed** — the count and per-file limits bound the total on their own.
That is a direct benefit of dropping the ceiling from 6 to 3, and it
removes the second number snag #40 asked for.

Base64 inflates attachments by roughly 37%; the Mailgun figure is from
documentation, not from an observed payload, and is worth confirming
before it is relied on.

## 4. Display requirement

**Files are listed, with sizes, at every point they exist.** Currently
only the last of the three is built.

| where | today | wanted |
|---|---|---|
| Create form | native control shows "2 files" — no names, no sizes, no removal | accumulating list: filename, size, remove control, running count |
| Preview | **nothing at all** (#39) | list of what will be sent, or an explicit "no photos attached" |
| Case page | built — `_message_card.blade.php:52`, filename + KB | same, units switched to MB above 1MB |

All three share one formatting helper so they cannot drift apart.
Absence is stated explicitly rather than inferred from silence — a blank
region reads identically to a successful upload and to a rejected one.

**This needs JavaScript on the create form, and that is unremarkable.**
The app already runs JS: the theme switcher at
`resources/views/layouts/app.blade.php:17-27`, Bootstrap's bundle at
line 310, and `@yield('scripts')` at line 311 as a per-page slot. An
earlier reading of snag #7 as a "no JS" policy was wrong — #7 observes
that one form has no JS, it does not forbid it. Charlie's stated
preferences are about styling (plain Bootstrap, nothing flashy), not
behaviour.

**The script is an enhancement, never a dependency.** If it fails to
load, the native `multiple` input still submits and `CaseController::store`
still enforces the ceiling, the mime types and the sizes. No evidential
guarantee rests on a script having run.

## 5. Defects folded in

Found while walking this on prod, 2026-08-09. Both logged separately as
snags #43 and #44.

**#43 — sequential browsing silently discards files.** Selecting one
photo, then browsing again for a second, keeps only the second: an HTML
file input replaces its entire `FileList` on each selection. Confirmed on
prod. The tenant believes three photos are attached and one is sent.
Multi-select in a single dialog stages correctly (verified: two files
selected together both staged). Fixed by the accumulating list in §4.

**#44 — the staged-photo message persists across abandoned cases.**
`preview.blade.php:34` links Edit to a bare `route('cases.create')`, so
`create()` (`CaseController.php:312-321`) cannot distinguish "returning
via Edit" from "starting a fresh case". The preview payload is one
session key per user, not per case, and is never cleared when a creation
is abandoned. A tenant starting a *new* case is therefore told "Your
photo is saved — you don't need to re-attach it", does not attach, and
`store()` then overwrites the payload with an empty photo array. Letter 1
goes out bare. The message actively talks the tenant out of attaching
evidence, and #39 means nothing catches it before sending.

Fix shape for #44: give Edit an explicit resume marker
(`route('cases.create', ['resume' => 1])`); treat a payload as resumable
only with that marker; otherwise clear it and delete the staged files.
Abandoned payloads currently also leave orphaned files in the per-user
preview folder.

## 6. Build sequence

**Now — letter 1.**

1. Add `attachments.first_notice_max` to `SettingController`, with a new
   range type (min 0, max 3). Seed the row; default **3**.
2. Read it in `CaseController::store` in place of the hardcoded `max:6`;
   re-check at `confirm()`, since a ceiling can change between staging
   and confirming.
3. Per-file limit to `max:4096`.
4. Error messages: name the file and state the limit in plain words.
   Today's string is "The photos.0 field must not be greater than 2048
   kilobytes" — an internal array index, a unit nobody thinks in, and
   form jargon. Cover `uploaded` and `mimes` too.
5. Create-form file list (§4) with accumulation and removal.
6. Preview file list (#39).
7. Case-page units to MB.
8. Fix #44's stale-payload leak.
9. Hide the file input entirely when the ceiling is 0.

**Later — with #19.** Reply attachments and
`attachments.tenant_reply_max`, built and shipped together (R5).

**Not built, ever, unless this note is revisited.** Attachments on sweep
letters (R1).

## 7. What this closes

- **#40** — the app-side half. Quantity and size both settled; the
  combined-size rule is designed out rather than added.
- **#39** — the preview list, as a named requirement rather than a snag.
- **#43, #44** — new, folded into the same pass.
- **#19** — unblocked design-wise; gains its ceiling key when built.

## 8. Open questions for Charlie

1. **Default letter-1 ceiling: 3?** Or lower for the trial, raised later.
2. **Resize-on-upload** — deferred here, not decided. Snag #40 argues for
   keeping the tenant's original on disk as the evidential record and
   attaching a compressed derivative. That would decouple photo quality
   from message size entirely and make the ceilings far less load-bearing.
   It also means the bytes the landlord receives are not the bytes held as
   evidence — a deliberate decision, to be recorded as one if taken.
3. **Confirm Mailgun's 25MB ceiling** against current documentation
   before the numbers in §3 are relied on.
4. **PDF as an attachment type** — currently permitted alongside JPG and
   PNG. Unchanged here, but worth a conscious yes: a PDF is not a photo,
   and the wording throughout the UI says "photos".

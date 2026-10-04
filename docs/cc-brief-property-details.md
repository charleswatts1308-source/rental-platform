# Brief — property details: type, lease flag, lease documents

**Branch:** `feature/property-details` — tag `pre-property-details` marks
the commit on `main` before it (`06a17b1`).
**Status: D0. This document is the deliverable. No code has been written.
Hard stop until D0 is accepted.**

---

## D0 — what was asked, what is already ruled, and what I found

### Asked (Charlie, 4 Oct 2026)

The property pages — entry and edit — gain three things:

1. **Property type**, a dropdown of the usual UK types plus "Other".
   **Mandatory.**
2. **Lease agreement upload**, as photographs, possibly several (page 1,
   page 2…). **Optional.**
3. **A yes/no flag: "Do you have a Lease agreement (even if you can't
   find it)?"**

### Already ruled, and not reopened here

- **Property type is information only, for statistics.** It drives no
  letter, no obligation, no branch in the escalation ladder. This is the
  ruling that keeps the change small, and it is the one to re-check if
  anyone later proposes reading the type in a letter.
- **Existing rows do NOT default to "Other."** They get their own value.
  "Other" must keep meaning *genuinely other*, or the statistics that
  justify the field cannot distinguish it from *pre-dates the question*.
- **The lease flag offers three answers, not two** — yes, no, don't know.
  "Even if you can't find it" asks whether one EXISTS. A lodger or an
  informal arrangement may genuinely not know, and a forced yes/no
  records a guess as a fact.
- **A LEASE NEVER LEAVES THE PLATFORM.** Never attached to a case, never
  sent to a landlord, never carried on a letter, never parsed by the
  application. It is visible to the tenant who uploaded it and to admin,
  and to nobody else.
- **Why it is held at all:** to identify the landlord's formal SERVICE
  ADDRESS if that is ever needed. A tenancy agreement normally states it.
  So the lease is reference material a HUMAN reads in order to decide
  what to put in the landlord contact — not an input the platform
  consumes. That purpose is the reason for the invariant above, and
  should be quoted alongside it rather than left implicit.

### What exists now — findings

**`properties`** is a thin table: `address_line1`, `address_line2`
(nullable), `city`, `postcode`, `registered_by_user_id`, timestamps.
No type, no document, no soft deletes. **There is no property DELETE
route** — index/create/store/edit/update only — which removes a whole
class of retention question from this brief.

**`file_attachments` EXISTS AND IS UNUSABLE AS IT STANDS.** Recreated
standalone in the May rentals removal and never wired to anything. Its
columns are `file_name`, `blob_url`, `content_type`, `file_size`,
`uploaded_date` — **no owner column of any kind**, no foreign key, no
disk, no scan status. Nothing reads or writes it.

**`message_attachments` is the live, working pattern** and the one to
follow: `case_message_id`, `disk`, `path`, `original_filename`,
`mime_type`, `size_bytes`, `direction`, `scan_status`.

**Case photographs are stored on the `local` disk** — private, not
`public` — and served through the application. Limits live in
`App\Support\PhotoLimits` (4 MB per file, a total budget, ceilings
settable per stage).

---

## Proposal

### 1. `property_type` — new column on `properties`

`string(32)`, **NOT NULL**, backed by a PHP enum `PropertyType`.

Proposed values — **this list needs Charlie's confirmation**:

| stored key | label |
|---|---|
| `detached` | Detached house |
| `semi_detached` | Semi-detached house |
| `terraced` | Terraced house |
| `end_terrace` | End-of-terrace house |
| `flat` | Flat or apartment |
| `maisonette` | Maisonette |
| `bungalow` | Bungalow |
| `studio` | Studio flat |
| `room_shared` | Room in a shared house |
| `park_home` | Park home |
| `other` | Other |
| `not_specified` | Not specified |

`not_specified` is the backfill value for rows that pre-date the
question. **It is not offered in the dropdown** — a tenant must choose a
real answer. It exists so the statistics can separate "they chose Other"
from "we never asked".

A free-text box beside "Other" is NOT proposed. It was not asked for, it
is a second field to validate and moderate, and the stated purpose is
counting. Easy to add later; hard to remove once populated.

### 2. `has_lease_agreement` — new column on `properties`

`string(16)`, **NOT NULL**, enum `LeaseAgreementAnswer`: `yes`, `no`,
`unknown`, plus `not_specified` for backfill, on the same reasoning.

**Open for Charlie:** mandatory on the form, or optional? With "Don't
know" available there is always an honest answer, so I propose
**mandatory**. Say if you would rather it could be skipped.

### 3. `property_documents` — new table

**Recommendation: a new, purpose-named table. Do NOT reuse
`file_attachments`.**

The reason is not tidiness. A generic "attachments" table invites
exactly the mistake the invariant forbids — in six months, attaching one
to a letter looks like a one-line change, because the table's name does
not say otherwise. `property_documents`, with the rule written at the
top of the model, says what it is. `file_attachments` stays unused and
is a separate question (it is already noted in the project memory as
never wired in).

Columns, following `message_attachments`:

- `property_id` — FK, cascade on delete
- `uploaded_by_user_id` — FK, restrict
- `kind` — string, `lease` for now; room for later without a new table
- `disk`, `path` — **`local` disk, never `public`**
- `original_filename`, `mime_type`, `size_bytes`
- `page_number` — nullable int, so "page 1, page 2" orders correctly and
  a tenant can re-upload one page without disturbing the others
- `scan_status`
- timestamps

**Accepted formats: images AND PDF.** The ask said photographs, but a
tenancy agreement is as often emailed as a PDF as photographed, and
refusing the format people already have is friction for no gain.

**Limits:** reuse `PhotoLimits` per-file size (4 MB) rather than
inventing a second scheme. A page count ceiling is proposed at 20.

**Serving:** through an authorised controller that checks ownership on
every request. No public URL, no signed URL that outlives the session,
no listing by guessable id.

### 4. Forms

Property **create** and **edit** both gain the type dropdown and the
lease flag. Uploads are proposed for the **edit** form only — a tenant
registering a property is usually mid-task on their way to raising a
case, and a multi-file upload at that moment is friction on the one path
that matters. Say if you want it on create as well.

---

## Migration plan

Three migrations, in order:

1. Add `property_type` and `has_lease_agreement` as **nullable**.
2. Backfill both to `not_specified`.
3. Alter both to NOT NULL.

Split because a single `NOT NULL` add against a non-empty table behaves
differently across MariaDB versions and would have to invent a default.

**MariaDB check before merge is mandatory** (`/CLAUDE.md`): migrate
against dev MariaDB, `SHOW CREATE TABLE properties` and
`property_documents`, confirm plain `datetime` columns with **no
trailing `ON UPDATE CURRENT_TIMESTAMP`** (trap #18), confirm the FKs,
column types and defaults are as intended, then roll back clean. The
suite runs SQLite in memory and **cannot** show this.

**Both long-lived boxes already hold properties** — gafol and prod — so
the backfill runs for real on both, and the deploy updates
`docs/environment-state.md` as its last step.

---

## Test plan

- Type is required on create and on edit; an unknown value is rejected.
- `not_specified` is NOT selectable from the form.
- The backfill migration leaves existing rows valid and distinguishable
  from a chosen `other`.
- Lease flag accepts all three answers and rejects anything else.
- A document uploads, lists in page order, and re-uploading page 2
  leaves pages 1 and 3 alone — **the #72 shape**, where a replacement
  wiped what the tenant had kept.
- **A document is NOT reachable by another signed-in user.**
- **A document never appears on a case, a letter, or a mailable.** This
  assertion is the invariant made executable, and is the reason for the
  separate table.

No existing assertion is weakened.

---

## Open questions for Charlie

1. **The type list above** — right set, right labels?
2. **Is the lease flag mandatory?** I propose yes.
3. **Uploads on the edit form only, or on create as well?** I propose
   edit only.

---

## D1 onwards, once D0 is accepted

D1 schema + enums + migrations with the MariaDB check. D2 forms and
validation. D3 the document upload, listing and authorised serving. D4
implementation report, then `--no-ff` merge.

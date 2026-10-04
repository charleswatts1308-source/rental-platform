# Implementation report — property details

**Branch:** `feature/property-details`, from `main` at `06a17b1`
(tag `pre-property-details`).
**Brief:** `docs/cc-brief-property-details.md` — D0 accepted 4 Oct 2026.
**Suite: 914 green**, up from 890. 24 tests added, none weakened.

---

## What was built

Three things asked for, all on the property pages:

1. **`property_type`** — mandatory dropdown, information only, for
   statistics.
2. **`has_lease_agreement`** — mandatory, three answers: yes, no,
   "I don't know".
3. **Lease pages** — optional upload on both forms, listed and removable
   on edit, served privately.

---

## The decisions that shaped it

### The backfill value is the whole design

Both columns carry a `not_specified` case that exists ONLY as a backfill
marker. It is not in `selectable()`, not in the markup, and refused by
validation — three tests pin that.

The reason is the stated purpose. These fields exist to be counted. Had
existing rows been defaulted to `other` and `no`, "the tenant chose
Other" and "we never asked" would have merged on day one, and nothing
later could unpick it, because the information was never recorded.

### No database default, deliberately

Both columns are NOT NULL **with no default**. A future code path that
forgets to supply a value fails loudly on insert rather than quietly
recording an answer nobody gave. The noise is the feature.

### Three migrations, not one

Add nullable → backfill explicitly → tighten to NOT NULL. A single-step
NOT NULL add against the non-empty `properties` table on gafol and prod
would have forced a database default, which is the one thing that must
not exist here.

### The type list is sourced, and that changed it

The list and its order come from the English PRS distribution already
published on `/prs` (Background), so renters.rent's own figures can be
read against the national ones. Two consequences that look like
mistakes and are not:

- **Flats are split**, purpose-built from converted, because the source
  splits them. Together they are 41% of the sector — the largest
  distinction in the data.
- **There is no end-of-terrace.** The source folds it into terraced.
  Offering it would move an unknown slice of the 34% into a category the
  national figures do not have, and the comparison stops working.

**"Room in a shared house" stays in plain English, not "HMO".** Charlie
raised that it is usually an HMO, and that is broadly right — three or
more people from more than one household sharing a kitchen or bathroom.
But HMO status is a legal classification about someone else's property,
and a tenant asked it will guess. A self-declared field can only
reliably collect what the person can see from where they stand. If HMO
status matters later it needs its own question, phrased around
observable facts ("how many other households share your kitchen?").

### The lease invariant, and why it is written three times

> **A property document never leaves the platform.** Never attached to a
> case, never sent to a landlord, never carried on a letter, never
> parsed by the application.

It is held so a HUMAN can read the lease to identify the landlord's
formal **service address** if that is ever needed — a tenancy agreement
normally states it. Reference material a person consults, not an input
the system consumes.

It is stated in the table migration, the model docblock and the
controller, and **it is now executable**: two tests assert a lease page
never surfaces on the tenant case page and never appears in
`message_attachments`.

**`file_attachments` was NOT reused.** It exists (recreated May 2026),
has no owner column of any kind and is wired to nothing. More
importantly a table called "attachments" does not carry the rule, and
in six months hanging one off a letter would look like a one-line
change. `property_documents` says what it is.

### Privacy of the files themselves

- **Private disk, served through PHP.** No public disk, no signed URL.
- **Every action authorises against the property** and checks the
  document belongs to it.
- **Stored names are random, not the tenant's filename** — an original
  name can carry a person's name, and a path is a thing that leaks. A
  test pins it.
- **Removal deletes the file as well as the row.**

### Presentation of the upload

Optional in validation was never the requirement. Charlie's condition
was that it must not read as a demand: this form is the one thing
standing between a tenant and raising a case, and anything resembling
required paperwork is how somebody gives up before starting. So: muted
label, no asterisk, "if you have it to hand", "or not at all", and a
line saying it is never sent to the landlord.

**It appears on create as well as edit.** The brief proposed edit-only
on friction grounds; Charlie overturned it with better reasoning — a
tenant registering a property usually has the lease open already,
because that is where the landlord's email address came from.

Label wording settled on walking it: **"Upload lease agreement —
optional"**, not "Lease agreement — optional".

---

## Deltas from the brief

| Brief said | Built | Why |
|---|---|---|
| Uploads on edit only | Both forms | Charlie, 4 Oct — the lease is already open during registration |
| Generic type list | Sourced list, flats split, no end-of-terrace | Comparability with the Background page |
| Page ceiling 20 | 20, enforced in the Action | unchanged |
| `PhotoLimits` per-file size | 4 MB, stated in the Action's own rules | `PhotoLimits` carries stage-dependent CASE ceilings that have nothing to do with a lease; borrowing them would couple two unrelated policies |

---

## MariaDB check — DONE, and it passed

Per `/CLAUDE.md`, migrated against **dev MariaDB**, inspected with
`SHOW CREATE TABLE`, rolled back.

- `property_type` → `varchar(32) NOT NULL`, **no default**.
- `has_lease_agreement` → `varchar(16) NOT NULL`, **no default**.
- `property_documents.uploaded_at` → `datetime NOT NULL`, plain.
- **No trailing `ON UPDATE CURRENT_TIMESTAMP` anywhere on either
  table** — trap #18 did not bite, despite the `change()` in step 3.
  Worth recording: `change()` was the thing most likely to trigger it.
- FKs: property **cascade** on delete, user **restrict**.
- Backfill confirmed on **15 real dev rows**, all `not_specified`.
- **Rollback clean** — `properties` came back byte-identical to its
  original definition and `property_documents` was gone.

Dev has since been re-migrated so the feature can be walked.

---

## Test deltas

**914 green, 890 before. 24 added, 0 weakened.**

Added — `PropertyDetailsTest` (12): both fields required; the backfill
marker refused on each; values outside the enums refused; both stored;
all three lease answers accepted; edit changes both; the marker absent
from the markup; the sourced ordering pinned; markers absent from both
`selectable()` lists.

Added — `PropertyDocumentTest` (12): stores and numbers pages; the
stored path is not the tenant's filename; a later page does not renumber
earlier ones (#72 shape); non-image non-PDF refused; PDF accepted; owner
served; **another signed-in user forbidden on view and delete**; guest
redirected; removal clears disk and row; **and the two invariant tests**.

Changed, not weakened: ten existing property payloads gained the two
mandatory fields, and one redirect assertion moved from `/properties` to
`/cases` because an edit now finishes where it was reached from.

**One of my own tests was wrong and is recorded here deliberately.** The
guest test passed for a bad reason: `actingAs()` signs a user in for the
REST of a Pest test, so the later "guest" request was still
authenticated. It proved nothing. It builds the row directly now. The
#74 lesson again — a test that agrees with you is not evidence.

---

## Deployment notes

**Four migrations** go to gafol and prod:

```
2026_10_04_100000_add_property_details_to_properties_table
2026_10_04_100100_backfill_property_details
2026_10_04_100200_make_property_details_required
2026_10_04_100300_create_property_documents_table
```

- **Both boxes already hold properties**, so the backfill runs for real
  on both. Expect every existing row to read `not_specified` on both
  columns afterwards — that is correct, not a failure.
- **No `composer.json` / `composer.lock` change**, so the prod composer
  trap (open action 1a) does not apply to this release.
- `storage/app/properties/` is written for the first time. Untracked,
  created on demand — but worth confirming the directory is writable on
  both boxes before the first upload.
- `docs/environment-state.md` is updated as the LAST step of each
  deploy.

---

## Open, and deliberately not built

- **No free-text box beside "Other".** Not asked for, a second field to
  validate and moderate, and the purpose is counting. Easy to add later;
  hard to remove once populated.
- **No virus scanning.** `scan_status` records `skipped` rather than
  `clean`, so the column never claims a check that did not happen. The
  case photo path is in the same position.
- **No HMO question.** See above — it needs observable-fact phrasing,
  not the term.
- **Nothing reads `property_type` anywhere yet.** It is collected and
  stored; no admin view counts it. That is the next obvious piece if the
  statistics are actually wanted rather than merely possible.

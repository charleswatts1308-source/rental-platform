# Landlord contact model — design gap and proposed direction

**Status:** design note, no code. Reached in discussion 2026-07-27.
**Ties to:** snag #24 (no way to correct a landlord email), snag #25
(no delivery-failure detection — the reason a typo goes unnoticed).
**Authority:** any build here touches the LLCS core and MUST be
reconciled against `docs/llcs-silence-model-design.md`, which wins over
this note and any brief.

---

## The omission

`properties` has **no landlord link at all**. The landlord contact is a
property of the **case** (`repair_cases.landlord_contact_id`), captured
once on the create-case form and never editable afterwards. There is no
"this property's landlord is X" anywhere in the model.

Two consequences:

1. **Re-entry.** The landlord email/name is typed afresh for every case,
   even for the same property.
2. **No correction path (snag #24).** A mistyped landlord email is
   permanent short of abandoning and re-raising the case.

This was flagged minor when first seen (18 Jul). It has since been hit in
real use (27 Jul) with a live email needing correction — promoting it
from a convenience item to a data-design decision.

## Why the current shape is the wrong shape

The landlord lives in `landlord_contacts`, a **global table keyed by a
unique email**, deduplicated in `CaseController::resolveLandlordContact`
(find-or-create by email, no user scoping).

The apparent justification was landlord-level aggregation — building a
picture of a landlord's responsiveness across cases ("strength in
numbers"). That justification does not survive contact with reality:

- **Email is not landlord identity.** A landlord or agency routinely uses
  *different emails per property*, and agents use *different inboxes per
  staff member*. So `property@agency.com` and `jsmith@agency.com` are one
  agent to a human but two rows to the system — the aggregate was never
  actually achieved.
- **The dedup cuts both ways wrongly.** It *over-merges* (two unrelated
  tenants who both type a shared `info@` agency inbox become one contact)
  and *under-identifies* (one landlord on two addresses becomes two
  contacts).
- **Editability makes email-as-key fragile.** The moment you allow the
  email to be corrected — the feature #24 asks for — any `GROUP BY email`
  history splits at the edit. A mutable natural key is a poor identity.

Conclusion: the email is a **contact channel**, and its natural grain is
the **property** (and it varies per property, per tenancy, per agent
staffer, over time). The global-by-email entity is the mis-modelling, not
the thing to protect. A *true* landlord identity — if the product ever
needs verified, cross-property reputation — cannot be derived from email
at all; it would be a deliberately-modelled entity that OWNS many contact
routes, identified by claiming/verification. That is a much larger, later
thing and is NOT required to fix what is blocking today.

## Integrity — does relocating the email threaten existing data?

**No.** The evidential spine already freezes every fact at the moment it
happens and never reads the live landlord record for history:

- `case_messages.to_address_raw` — recipient snapshotted at send
  (`SendCaseNotice.php:116`).
- `reply_tokens.bound_email` — snapshotted at token issue
  (`SendCaseNotice.php:105`).
- `case_messages.from_address_raw` — sender recorded at receipt
  (`HandleInboundReply.php:110`).
- `body_raw` / `subject` — frozen at send, including the landlord name
  baked into the letter.
- The escalation counter derives purely from `stage_at_send` rows — no
  dependency on the landlord email whatsoever.

So editing or relocating the email cannot rewrite a single past fact. The
live contact is only ever a **source for the next action**.

The live email is read in exactly three forward-looking places — the real
surface area of the change, not an integrity risk:

1. `SendCaseNotice` — recipient + token binding + letter name for the
   *next* send.
2. `HandleInboundReply:102` — the quarantine sender-match compares an
   inbound From against the *live* landlord email.
3. `CaseController` — create/resolve + display.

`verified_at` and `invited_by_user_id` on `landlord_contacts` are defined
but **used nowhere** in logic — retiring the entity is behaviourally free.

New design point (a feature concern, not existing damage): correcting an
email while a reply token is in flight leaves that token bound to the old
address, so a correction should trigger a fresh send (new token).

## Proposed direction

### New table `property_landlord_contacts` (per-property, versioned)

| column | notes |
|---|---|
| `id` | |
| `property_id` | FK → `properties` |
| `email`, `name`, `role`, `organisation_name` | the contact details |
| `created_by_user_id` | who entered / corrected it |
| `effective_from` | when this version took over |
| `superseded_at` | nullable; set when a newer version replaces it. Current = `superseded_at IS NULL` |

- **No global unique on email** — deliberate; email is per-property and
  may legitimately repeat.
- Change-history is intrinsic: a correction inserts a new row and
  supersedes the old, so the property carries its own contact timeline.

### `repair_cases`

Replace `landlord_contact_id` → `property_landlord_contact_id`, pointing
at the **version in force when the case was raised** (preserves "which
contact this case used").

### Retire `landlord_contacts`

Drop it (and the unused `verified_at` / `invited_by_user_id`) after
backfill.

### Snapshots unchanged

`to_address_raw`, `bound_email`, `from_address_raw` keep freezing exactly
as now; only their *source* changes.

### New UI

- Edit landlord contact on the **property** page → inserts a new version,
  supersedes the old (the direct fix for #24), plus a small contact-history
  list.
- Case creation **inherits** the property's current contact (prefill).

### Backfill

For each existing case: take its current landlord contact's fields plus
the case's `property_id`, find-or-create a `property_landlord_contact`
scoped to that property, and repoint the case. Latest per property becomes
the current version.

## Effort estimate

| Component | Size |
|---|---|
| D0 design report (required before code) | S |
| Schema: new table + FK swap + data backfill + drop old table | M |
| Models / relationships (new `PropertyLandlordContact`, remove `LandlordContact`) | S |
| Read-site refactor (SendCaseNotice, HandleInboundReply, CaseController) | S–M |
| Property edit-contact UI + change-history view | M |
| Case-create prefill / inherit | S |
| Test updates + new versioning/edit/inherit tests (no weakened assertions) | M–L (dominant cost) |
| MariaDB manual schema check + `--no-ff` merge | S |

Rough total: **~5–6 focused days** (a couple of weeks part-time). The test
surface and the edit UI dominate; the schema itself is straightforward.

## Decision — one contact per property, no per-case override (Model A)

**Resolved 2026-07-27.** A property has exactly ONE landlord contact at a
time, versioned over time (the `superseded_at IS NULL` row is current). A
case inherits the property's current contact and CANNOT override it.

**Rationale (the guiding principle):** the contact address on the tenancy
agreement is the **legally-required service address**. The platform's job
is to serve notice to that one correct address; how the recipient
circulates it internally (agent to owner, staffer to staffer) is the
recipient's concern, not something we model. So there is no need for
concurrent agent-vs-owner contacts or a per-case override — there is one
correct address, and it is the one on the agreement.

This kills the Model B alternative (multiple concurrent contacts per
property, by role). If a genuine need for concurrent recipients ever
appears, it stays an ADDITIVE change later, because each case already
records the exact contact row it used — but it is explicitly out of scope
now and not expected.

Consequence for the schema above: `role` is retained as descriptive
metadata (agent / owner / etc.) but is NOT a selector — it never
determines routing. Routing is always "the property's current contact".

## Sequencing note (from #24)

#25 (no delivery-failure detection) is what makes a typo *visible* in the
first place. Building a correction path without it lets a tenant fix a
mistake they have no way of knowing they made. Order: make failures
visible, then make them correctable.

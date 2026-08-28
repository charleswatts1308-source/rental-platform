# Implementation report — property-owned landlord contacts (Model A)

**Branch:** `feature/property-landlord-contacts` (7 commits + D0, plus
a walk fix on 28 Aug — see §10)
**Tag on main before branching:** `pre-property-landlord-contacts`
**Merged:** not yet. Not pushed.
**D0:** `docs/cc-report-property-landlord-contacts-d0.md`
**Closes:** snag #24, snag #49(a), snag #49(b)

---

## 1. Suite

| Point | Tests | Assertions |
|---|---|---|
| Baseline on main | 578 | 2364 |
| After commit 7 | 638 | 2506 |

**The stated baseline of 377 was stale.** Main was green at **578** on a
clean tree before any work started. Nothing was wrong; the number in the
brief was simply out of date, and 578 was used as the working baseline.

Net +60 tests. The suite was green before every commit.

## 2. Commits

| # | Commit | What |
|---|---|---|
| 0 | `e1a7cbb` | D0 report |
| 1 | `5734d4f` | create `property_landlord_contacts` |
| 2 | `8712109` | model, `Property` relations, versioning, 13 tests |
| 3 | `5a95243` | `cases.property_landlord_contact_id` + backfill class + data migration, 13 tests |
| 4 | `42d0cea` | flip all nine read sites, factory rewire, 14 tests |
| 5 | `d2e45aa` | create inherits; closes #49; 6 replacing tests |
| 6 | `8245b3e` | property correction UI, history, case event; closes #24; 19 tests |
| 7 | `d5e1f9f` | drop `landlord_contacts`; fixture rewrite |

Commits 1–3 are additive and roll back clean. Commit 7 is separable and
can be held to a second deploy.

## 3. MariaDB checks

Run against dev MariaDB 12.1.2, `rental_platform`, with real data
(5 cases, 3 properties, 3 legacy contacts).

**New table.** `SHOW CREATE TABLE property_landlord_contacts` confirmed
`effective_from` and `superseded_at` as plain `datetime` with **no
trailing `ON UPDATE`** (issue #18), `created_at`/`updated_at` as
`timestamp NULL`, `UNIQUE (property_id, is_current)` present, FKs
restrict as intended and `superseded_by_user_id` as `ON DELETE SET NULL`.

**The invariant, proven on the real engine.** SQLite cannot vouch for
this, so it was exercised directly inside a rolled-back transaction:

- two superseded (`is_current` NULL) rows on one property — accepted
- two current (`is_current` = 1) rows on one property — **rejected** by
  the unique key

**Altered `cases`.** `SHOW CREATE TABLE` after each schema commit. The
provenance column landed as `bigint(20) unsigned DEFAULT NULL` with a
restrict FK; the nullable-ing of `landlord_contact_id` kept its FK; the
final drop removed both column and constraint and left the provenance FK
intact. No `ON UPDATE` anywhere.

**Rollback.** Each schema migration was rolled back and re-applied on dev
MariaDB. After rolling back the backfill, 0 contact rows remained and all
5 cases were intact. After rolling back and re-applying the drop, the
table and column returned and then went again, with cases and property
contacts untouched.

**Backfill on real data.** Property 3 carried three different landlord
emails across five cases. The backfill produced a correct three-version
chain — `ll@ll.com` → `charles.watts1308@gmail.com` →
`jcwatts99@outlook.com`, only the last current — repointed all 5 cases,
left 0 unrepointed and found 0 orphans. The legacy table was snapshotted
to JSON outside the repo before the drop.

## 4. Assertion deltas

No assertion was weakened. Three deliberate changes, all strengthening:

**Two `landlord_contacts` dedup tests replaced (commit 5).** They
asserted find-or-create behaviour on a table nothing reads any more. Six
tests replace them, asserting the contact a case is actually *served* on
— including the exact gafol reproduction from #49: a second tenant types
"Larry Landlord" and the letter must not open "Dear C Watts".

**Two `RepairCase` relation tests replaced (commit 7).** They covered the
retired `belongsTo`. Three replace them, covering the provenance relation
and — the important one — that routing does **not** follow it.

**`LandlordContactTest` deleted (commit 7).** Six tests of a model that
no longer exists.

**Fixtures across 12 files.** The old
`LandlordContact::factory()->create([...])` +
`'landlord_contact_id' => $contact->id` pair became
`RepairCase::factory()->withLandlord([...])`, carrying the same landlord
attributes through, so every downstream assertion sees the email and name
it saw before.

## 5. Verified, not assumed

Both #49 tests were run against commit 4 (pre-fix) and **confirmed to
fail** before the fix was written.

`case_messages` is asserted byte-identical either side of a backfill
(commit 3), a routing correction (commit 4) and a UI correction
(commit 6) — `DB::table('case_messages')->get()` compared whole.

## 6. Deviations from the D0 plan

**Commits 4 and 5 are not independently green.** Flipping the readers
makes the send path require a property contact, which only the create
path writes. Rather than merge them, commit 4 carries a minimal bridge in
`confirm()` that mirrors the legacy row into the property when absent —
behaviour bit-for-bit as it was, #49(a) included — and commit 5 fixes #49
deliberately with tests that fail first.

**Commit 5 gained a migration** making `cases.landlord_contact_id`
nullable. Not in the plan, which put schema in 1/3/7. Justified: nothing
reads the column after commit 4, and continuing to write it would only
manufacture junk rows for commit 7 to drop.

**Two schema choices added beyond the D0 table.** `is_current` (the
enforcement half of the invariant) and `source` (provenance of backfilled
rows) were both in the D0; `superseded_by_user_id` was added during
implementation so the history can say who made a correction.

## 7. What is NOT covered

- **`migrate:fresh` on MariaDB was not run.** The dev DB user has no
  `CREATE DATABASE` right, so a scratch database could not be made, and
  running it against `rental_platform` would have destroyed dev data. The
  full chain from scratch — `landlord_contacts` created, backfilled, then
  dropped — is exercised by every SQLite suite run, but not on MariaDB.
  **Worth doing on gafol before prod.**
- **Browser walk: DONE 28 Aug, partially.** The whole flow was driven
  against the running app over real HTTP with a real session — register
  property, raise a case, correct the address, raise a second case,
  inspect the returned HTML. See §10. **It found one real defect.** What
  it could NOT cover, because no browser automation is available here:
  JavaScript execution and visual layout. Specifically the create form's
  **multi-property toggle has never been exercised** — the `data-contact-*`
  attributes are confirmed correct in the markup, but nothing has run the
  script. A human at a browser is still needed for that one thing.
- **The backfill has never run on gafol or prod data.** It ran on dev's
  5 cases. Prod's shape is unknown from here — in particular the orphan
  count, which is reported at migrate time and belongs in the ledger.
- **The migration wrapper** is untested by construction; only the
  extracted backfill class is covered.
- **`docs/environment-state.md` is not updated** — nothing has been
  deployed yet. It gets updated as the last step of the deploy, per
  CLAUDE.md.

## 8. Behaviour changes to be aware of

**A reply from a superseded address is now quarantined.** Previously it
matched the case's own contact. Intended — the old address is no longer
the landlord of record — and asserted by test, but it is a change to a
previously-tested path.

**A case can no longer override its property's landlord.** A second case
at a property with a stored contact ignores anything typed. This is Model
A working as ruled, but it is a visible change for anyone used to
retyping the address per case.

**Correcting a contact does not send anything.** Deliberate, per §8 of
the D0: auto-sending would take the non-first branch of `SendCaseNotice`
and escalate the case as the price of fixing a typo. A non-escalating
"resend to the corrected address" action remains out of scope and needs
its own ruling against the silence design doc.

## 9. Open question for the next session

The design note's suggestion that a correction should issue a fresh token
was rejected on D3 grounds. What remains genuinely useful is a
**non-escalating resend** — letting a tenant push the current letter to a
corrected address without advancing the counter. That needs a decision
about `stage_at_send` semantics before it can be built, and that decision
belongs against `docs/llcs-silence-model-design.md`, not here.

---

## 10. Browser walk — 28 Aug

Driven against `artisan serve` on the dev MariaDB with a real session
(no browser automation available, so: real HTTP, real middleware, real
CSRF, HTML read back and inspected).

Walked: register property → create form with no stored contact → stage →
preview → confirm → property landlord page → correct the address →
history → create form WITH a stored contact → second case submitting no
landlord fields at all → preview → confirm → case show → properties
index → property edit.

**#24 confirmed working end to end in the real app.** Mailpit received
two letters: the first addressed to `typo@example.com`, the second to
`correct@example.com`, each carrying its own reply token. The correction
changed where the next letter went and left the first one alone.

Also confirmed by eye: the email normalises to lower case on save; the
history renders newest-first with the Current badge on the right row and
the superseded row marked "replaced"; the postal address displays and
does not appear in the letter; the second case inherits with **no
landlord fields submitted at all**; no error page anywhere in the walk.

### The defect it found — `d7aba9a`

Both landlord blocks on the create form were written as
`class="col-12" @class([...])`. Blade's `@class` emits a full class
attribute, so the tag carried **two**, and a browser keeps the first and
drops the second. `d-none` never applied.

A tenant on a property that already had a landlord would have seen the
read-only "This property's landlord" panel **with the editable landlord
fields still underneath it** — an edit the server would then ignore,
since those fields are excluded from validation in that state.

Display only: routing, validation and the letters were correct
throughout. But it is the #46/#49/#53 pattern again — a surface claiming
what the system will not honour — which is exactly why the walk was worth
doing. No behavioural test could have caught it; the fix carries three
regression tests that assert the tag rather than the styling.

### Known limitation, not fixed

With **JavaScript off** and **more than one property**, the create form
renders the editable landlord fields regardless, because the server
cannot know which property will be chosen. A tenant could type an address
that is then ignored in favour of the stored contact. It degrades safely
— the D13 preview renders from the stored contact, so the discrepancy is
visible before anything is sent — but it is a live example of the same
pattern. Flagged, not fixed; wants a decision rather than a patch.

# D0 — Property-owned landlord contacts (Model A)

**Status:** accepted 2026-08-27. Implementation follows on branch
`feature/property-landlord-contacts`, tag `pre-property-landlord-contacts`
on main.
**Supersedes the build direction in:** `docs/landlord-contact-model-gap.md`
(that note remains the record of how the decision was reached; where this
report and the note disagree, this report is what was built — see §9).
**Reconciled against:** `docs/llcs-silence-model-design.md`, which wins
over this report.
**Closes:** snag #24, snag #49(a). Closes #49(b) as a consequence.

---

## 1. What is wrong today

The landlord contact hangs off the **case** (`cases.landlord_contact_id`)
and lives in `landlord_contacts`, a global table with a **unique index on
email**, resolved find-or-create with no user scoping
(`CaseController::resolveLandlordContact`). `properties` has no landlord
link at all.

Three consequences, all snagged:

- **#24** — a mistyped landlord email is permanent. There is no edit route
  on a case after the first send; the only recovery is to abandon the case
  and raise a new one, losing the reference.
- **#49(a)** — first write wins globally. Whoever names an address first
  names it for every tenant, and a landlord name typed on a later case is
  discarded silently.
- **#49(b)** — the create-case preview renders the *typed* name while the
  send renders the *stored* name. The tenant approves one letter and a
  different one leaves. Reproduced twice on gafol.

## 2. Decision — Model A

A property has exactly **one** landlord contact at a time, versioned over
time. The current version is the row with `superseded_at IS NULL`. A case
**inherits** the property's contact and cannot override it.

Ruled 2026-07-27. The rationale is that the contact on the tenancy
agreement is the legally-required **service address**: there is one correct
address, and how the recipient circulates a notice internally is the
recipient's concern, not something this system models. Model B (concurrent
contacts per property, selected by role) is dead. If concurrent recipients
are ever genuinely needed it stays an additive change, because every case
records the contact row it opened with.

`role` is retained as descriptive metadata. It never selects a recipient.

## 3. The evidential constraint

`case_messages` freezes `to_address_raw`, `body_raw` and `subject` at send
time. **Nothing in this work writes, backfills, re-renders or migrates any
of those three.** Sent letters remain byte-identical.

The only adjacency: the expression that *feeds* `to_address_raw` at the
moment of a send changes from `$case->landlordContact->email` to the
property's current contact email. Same column, same moment, same freeze.

This is what makes the whole change safe, and it was verified before the
plan was written — see snag #24's Notes, which reach the same conclusion
independently.

## 4. Target schema

### New table `property_landlord_contacts`

| column | type | notes |
|---|---|---|
| `id` | bigint | |
| `property_id` | FK → properties | restrict on delete |
| `email` | string 255 | **no unique index** — deliberate |
| `name` | string 255 nullable | |
| `role` | enum landlord/agent | descriptive only |
| `organisation_name` | string 255 nullable | |
| `address_line1` | string 255 nullable | landlord postal address |
| `address_line2` | string 255 nullable | |
| `city` | string 100 nullable | |
| `postcode` | string 20 nullable | |
| `created_by_user_id` | FK → users | restrict |
| `effective_from` | **datetime** | when this version took over |
| `superseded_at` | **datetime** nullable | null = current |
| `superseded_by_user_id` | FK → users nullable | null on delete |
| `is_current` | tinyint nullable | 1 when live, NULL when superseded |
| `source` | enum entered/backfilled | provenance of the row |
| `created_at` / `updated_at` | timestamps | |

**`UNIQUE (property_id, is_current)`** enforces "one current contact per
property" in the database. MariaDB has no partial indexes; NULLs do not
collide in a unique index on either MariaDB or SQLite, so the nullable
`is_current` flag gives the same guarantee on both engines. Without this
the invariant is prose only, and a double-submitted edit forks a property
into two "current" contacts.

`effective_from` and `superseded_at` are **`datetime`, not `timestamp`** —
CLAUDE.md issue #18, the implicit `ON UPDATE CURRENT_TIMESTAMP` trap. These
are decision timestamps and must never move.

**Landlord postal address is store-and-display only.** It is captured on
the property, shown on the property page and in contact history, and never
reaches `buildLetterVars` or any template. Ruled 2026-08-27.

### `cases`

Gains `property_landlord_contact_id`, nullable, FK restrict. It records
**what the case opened with. It is provenance and is never used for
routing.** Loses `landlord_contact_id` in the final commit.

### Retired

`landlord_contacts` is dropped, along with its unused `verified_at` and
`invited_by_user_id` columns, the `LandlordContact` model and its factory.

## 5. Routing rule

**Every forward-looking read resolves `property->currentLandlordContact`.**

This is the single most important departure from the design note, which
says the case FK points at "the version in force when the case was raised"
*and* that property-level editing is the fix for #24. Those contradict: if
routing follows a frozen per-case FK, correcting the property changes
nothing about where the case's next letter goes and #24 stays open. One
column cannot be both a historical record and a live pointer.

Nothing is lost by making routing live, because
`case_messages.to_address_raw` already holds the per-send truth.

## 6. Call sites

Nine files, not the three the design note claims.

| Site | Change |
|---|---|
| `SendCaseNotice` :105 `bound_email`, :116 `to_address_raw`, :179 `Mail::to`, :302 `landlord_name` | resolve via property |
| `SendExhaustionCloser` :76, :86, :100, :133 | resolve via property (**missing from the note entirely**; it issues reply tokens and stamps recipients) |
| `HandleInboundReply` :102 quarantine sender-match, :208 tenant-notification name | resolve via property |
| `SilenceSweep` :616, :664, :711 | resolve via property (three tenant-facing nudges) |
| `CaseController` :82/:96/:167/:179 display, :526/:559 resolve | inherit; find-or-create deleted |
| `Admin\CaseOversightController` :37 + `admin/cases/show.blade.php` :67 | eager load new relation |
| `cases/show.blade.php` :93-95, `cases/create.blade.php` :207 | display |
| `DevCase`, `DevReply`, `DevReset` | create / truncate the new table |
| `RepairCaseFactory` :28 | property's contact — largest test-surface change |

## 7. Migration of existing data

`cases.property_id` is `NOT NULL`, so **every case maps**.

Per property, cases are walked in `opened_at` order. Each time the contact
email changes from the previous case's, the prior version is closed
(`superseded_at` = the new case's `opened_at`, `is_current` = NULL) and a
new version opened. The last version per property is left current. Every
case repoints to the version in force at its own `opened_at`.

**Rows that cannot be mapped.** `landlord_contacts` rows with zero cases —
typo junk (snag #24's second-order note) and `dev:` seed residue — have no
property and therefore no destination. They are **dropped, not migrated**.
Before the drop the whole table is snapshotted to SQL outside the repo, and
the orphan count is recorded in the implementation report and the
deployment ledger. There is no defensible home for them; the honest move is
to state how many were discarded.

**Reconstructed timelines.** Where a property genuinely carried two emails
across two cases, the chain above asserts "the contact changed on date X".
That is a reconstruction, not an observation. Such rows are marked
`source = backfilled` so the contact-history view can present them as
derived from case records rather than as a change somebody made. Otherwise
the change-history feature's first act is to show the user a history that
never happened.

**Testability.** The backfill logic lives in an invokable class that the
migration calls, not inline in the migration body. Migrations are not
reachable by the suite; a class is.

## 8. What closes, and how

**#49(a)** closes structurally, not by a fix. There is no global email key
any more — no unique index, and the find-or-create is deleted outright.
`properties` is already scoped per user (`registered_by_user_id`, no
cross-user dedup), so a contact hanging off a property is inherently
per-tenant. Two tenants typing the same `info@agency.com` get two
independent rows.

**#49(b)** dies as a consequence. The create form has two states: the
property has no contact, so the typed fields create version 1; or it has
one, and the fields are shown read-only with a link to correct them on the
property. With no overridable input, preview and send read one row and
cannot disagree. Asserted by test, not assumed.

**#24** closes via the property edit page. A correction inserts a new
version, supersedes the old, and every subsequent send on every open case
at that property goes to the new address. No abandon-and-re-raise.

### Correction does NOT auto-send

The design note says a correction "should trigger a fresh send (new
token)". **Rejected.** `SendCaseNotice` sets
`stage_at_send = current_stage + 1` on any non-first send, so an automatic
resend would **escalate the case as the price of fixing a typo** — a direct
violation of D3, which is increments-only and explicitly never resets.

What happens instead: a `landlord_contact_corrected` case event is written
on every open case at that property, recording from → to → when → by whom.
The in-flight reply token is left alone; it is evidentially harmless, and
the quarantine sender-match now guards the old address. The next scheduled
send binds a token to the corrected address.

A non-escalating "re-send the current letter to the corrected address"
action is useful and out of scope. It requires a send mode that leaves
`stage_at_send` unchanged, which is a change to the counter and needs its
own ruling against the silence design doc. Snag #24's Fix note anticipates
exactly this question.

### Known behaviour change

After a correction, an inbound reply from the **old** address now fails the
`HandleInboundReply` sender-match and is quarantined, where previously it
matched. Believed correct — the superseded address is no longer the
landlord of record — but it is a change to a tested path, not a no-op.

## 9. Where this departs from `landlord-contact-model-gap.md`

1. **Routing.** The note's per-case FK cannot fix #24 (§5). Routing is live
   off the property; the case FK is provenance only.
2. **Call-site count.** The note says three; it is nine. It misses
   `SendExhaustionCloser`, three sweep sites, the admin oversight
   controller and three dev commands. The effort estimate is
   correspondingly light.
3. **Auto-send on correction.** Rejected as a D3 ratchet violation (§8).
4. **Uniqueness.** The note specifies no constraint at all. Model A's
   central invariant needs a database index, not prose (§4).
5. **`role` "purely descriptive".** `DevReply:122-129` still branches on it
   for letter sign-off. Harmless, but not accurate.
6. **Backfill honesty.** The note's find-or-create backfill silently
   fabricates a change timeline. Hence `source` (§7).
7. **Sequencing.** The note orders this after #25. Superseded by the
   2026-08-24 ruling in the snagging list, which makes #24 independent and
   separately released.

## 10. Test plan

New coverage:

- one-current-per-property enforced at DB level (second insert throws)
- correction supersedes rather than mutates
- case create inherits and cannot override
- preview salutation and frozen `body_raw` salutation identical when a
  stored name differs from a typed one — **this test fails today; it is
  #49(b)**
- send after correction reaches the new address while every prior
  `case_messages` row is byte-identical
- inbound reply from a superseded address quarantines
- the backfill class against a seeded legacy pre-state
- landlord postal address round-trips and never appears in letter vars

Standing rule: **no weakened assertions**. The factory rewire touches 16
test files and is the classic route to accidental softening; every delta
goes in the implementation report.

## 11. Where the suite is blind

- **No existing test raises a second case against the same landlord
  email.** That single gap is why #49 survived from #7 for months.
  Everything in the suite today is one-case-per-landlord.
- **MariaDB schema.** The suite is SQLite in-memory. It cannot show the #18
  `ON UPDATE` trap, nor confirm `UNIQUE (property_id, is_current)` behaves
  as intended on the real engine. Manual `SHOW CREATE TABLE` against dev
  MariaDB before merge, for the new table and for altered `cases`.
- **The migration wrapper** stays untested by construction; only the
  extracted backfill class is covered.
- **No coverage of a contact change mid-case** — clock, counter or token.
  Unreachable today, reachable the moment this ships.

## 12. Commit order

0. This report.
1. Create `property_landlord_contacts`. Schema only. MariaDB check.
2. `PropertyLandlordContact` model, `Property` relationships, factory.
3. Add nullable `cases.property_landlord_contact_id` + backfill class +
   backfill migration. Old column and table still authoritative.
   **Reversible checkpoint — everything to here is additive.**
4. Flip all nine read sites; rewire `RepairCaseFactory`; update tests.
5. Case create inherits; free-text override removed; preview and send read
   one source. Closes #49(a) and (b).
6. Property edit-contact UI, contact history, correction case event.
   Closes #24.
7. Drop `cases.landlord_contact_id`, drop `landlord_contacts`, delete
   model/factory, update `DevReset`/`DevCase`. MariaDB check.
   **Deliberately last and separable** — can be held to a second deploy so
   a schema problem cannot force a rollback of working behaviour.

Then the implementation report, `--no-ff` merge on a green suite, and the
deployment ledger updated as the last step of any deploy.

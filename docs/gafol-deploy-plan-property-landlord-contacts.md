# gafol deploy plan — property-owned landlord contacts

**Branch:** `feature/property-landlord-contacts`, 16 commits, suite 701 green.
**Closes:** #24, #49(a), #49(b), #59.
**Written:** 2026-08-28. Nothing done yet; nothing pushed.

This is the pre-merge gate. gafol proves the migration chain against real
MariaDB with real data before `main` ever sees it.

---

## Two decisions needed before anything starts

### 1. Plesk Git tracks `main`, not a branch

The ledger says gafol's Plesk repo `laravel_093fde` tracks **`main`**.
This branch is not on main and must not be merged until gafol is clean —
that is the whole point of the gate. So one of:

- **(a) Repoint the Plesk repo** to `feature/property-landlord-contacts`
  for this deploy, then back to `main` after merge. Cleanest if Plesk
  allows it — prod's repo is locked to `master`, but gafol's may not be.
  **Check the panel first.**
- **(b) Push the branch and pull it by hand** on the box, leaving Plesk
  Git alone.
- **(c) Merge to main first and deploy main.** Rejected — it inverts the
  gate. If gafol finds a schema problem, main is already carrying it.

Either way **the branch has to be pushed**, which needs your explicit
say-so. Nothing is pushed today.

### 2. `migrate:fresh` — I think we should skip it, and here is why

I flagged this as an outstanding gate. On reflection that was the wrong
emphasis, and the correction matters more than the original point:

**The incremental migration is what prod will actually do.** gafol and
prod both migrate forward over existing data. That is the path with real
risk — the backfill reading real rows, the FK swap, the `DROP TABLE` —
and it is exactly what this deploy exercises.

`migrate:fresh` proves something different: that a brand-new environment
can be built from zero. Useful, but it is not prod's path, and running it
on gafol **destroys the staging data** — including the 8 D14 live-fire
cases the ledger records as reference material.

**Recommendation: do not run `migrate:fresh` on gafol.** Take the
incremental run as the gate, and leave from-scratch verified by the
SQLite suite (which runs the full chain on every single test run) until
there is a throwaway MariaDB to do it properly on.

Say if you want it anyway and I will plan around preserving the data.

---

## Step 0 — reconcile the ledger FIRST

Not optional and not merely overdue. This branch carries five migrations
including a `DROP TABLE`, and the ledger has not been checked against
reality since **27 Jun**.

Via Plesk → Laravel Toolkit (or the panel's artisan runner):

```
php artisan migrate:status
```

Compare against `docs/environment-state.md`. If they disagree, **stop and
fix the ledger before deploying** — the DB's `migrations` table is the
source of truth and the ledger is its mirror. Do not proceed on a ledger
that is known wrong.

## Step 1 — capture the "before" picture

Record these, they go in the ledger entry and one of them is the number
that matters most:

```sql
SELECT COUNT(*) FROM landlord_contacts;
SELECT COUNT(*) FROM cases;
SELECT COUNT(*) FROM properties;

-- The orphan count: contacts with no case. These have no property to
-- attach to and are DISCARDED by the drop. This is the one irreversible
-- number in the whole deploy.
SELECT COUNT(*) FROM landlord_contacts
WHERE id NOT IN (SELECT landlord_contact_id FROM cases WHERE landlord_contact_id IS NOT NULL);
```

## Step 2 — snapshot `landlord_contacts` before it is dropped

Non-negotiable. The table is destroyed in the final migration and the
orphan rows are not recoverable from anywhere else.

Plesk → Databases → phpMyAdmin → export `landlord_contacts` as SQL, keep
it off the box. (Same shape as the JSON snapshot taken on dev.)

## Step 3 — deploy the code

Per whichever route was chosen above. Then, as with previous gafol
deploys:

```
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:clear
php artisan view:clear
```

## Step 4 — migrate, and read what it prints

```
php artisan migrate --force
```

Five migrations, in this order. **Two of them print, and both lines
matter:**

1. `create_property_landlord_contacts_table`
2. `add_property_landlord_contact_id_to_cases_table`
3. `backfill_property_landlord_contacts` — prints
   `backfill: N versions across M properties, K cases repointed, J orphan
   contacts will be discarded with the table`
   **Copy that line verbatim.** `K` must equal the case count from
   Step 1.
4. `make_cases_landlord_contact_id_nullable`
5. `drop_landlord_contacts_table` — prints
   `dropped landlord_contacts: N rows discarded`

If the backfill line reports fewer cases repointed than exist, **stop**.
Every case has a `property_id`, so every case should map; a shortfall
means something the dev data did not contain.

## Step 5 — schema check (the CLAUDE.md Migrations rule)

```sql
SHOW CREATE TABLE property_landlord_contacts;
SHOW CREATE TABLE cases;
```

Confirm, against what dev produced:

- `effective_from` and `superseded_at` are **`datetime`** with **no
  trailing `ON UPDATE CURRENT_TIMESTAMP`** (issue #18)
- `UNIQUE KEY (property_id, is_current)` present
- `cases.landlord_contact_id` and its FK are **gone**
- `cases.property_landlord_contact_id` present, nullable, FK to
  `property_landlord_contacts`
- `landlord_contacts` no longer exists

Then prove the invariant on gafol's own engine, inside a transaction that
is rolled back:

```sql
START TRANSACTION;
-- two superseded rows on one property: should be ACCEPTED
-- two current rows on one property: should be REJECTED by the unique key
ROLLBACK;
```

## Step 6 — verify the data landed sensibly

```sql
-- Should be zero.
SELECT COUNT(*) FROM cases WHERE property_landlord_contact_id IS NULL;

-- Should be at most one current contact per property, always.
SELECT property_id, COUNT(*) FROM property_landlord_contacts
WHERE superseded_at IS NULL GROUP BY property_id HAVING COUNT(*) > 1;

-- Spot-check a property that had several cases: the chain should read as
-- a sensible timeline, all rows source='backfilled'.
```

## Step 7 — walk it in the browser

gafol runs the Mailgun **sandbox, outbound only**, so a real landlord
round-trip is not available — expected, per the Mail rule, not a fault.
What can be walked:

- an existing case still shows its recipient
- `/properties` shows the new **Landlord** button
- the landlord page renders, with history showing backfilled rows
  labelled **"Reconstructed from earlier cases"** rather than attributed
  to a person
- correcting an address creates a version, and the case events show the
  correction on open cases only
- the create form inherits on a property that has a contact
- **the preview shows the recipient block** (#59)
- raise a case and confirm the sandbox send goes to the current address

## Step 8 — the ledger, as the LAST step

`docs/environment-state.md` gets: the commit/tag landed, the date, the
five migrations, the backfill line verbatim, the orphan count discarded,
and what was verified. Reconciled against `migrate:status`.

**A deploy is not done until the ledger says it happened.**

---

## If it goes wrong

Migrations are forward-only in practice here. The drop is **irreversible
for data** — `down()` restores the table's shape but not its rows, which
is why Step 2 exists.

The useful property of the commit order: everything up to and including
the backfill is **additive**. If a problem appears before Step 4's
migration 5, both FK columns still exist and the old table is still
populated, so the old code still runs. The drop is the point of no
return, and it is deliberately last.

If something surfaces after the drop, the fix is forward — a new
migration — not a rollback against populated data.

---

## After gafol is clean

`--no-ff` merge to `main`, tag, then prod on its own schedule. Prod is a
separate decision and a separate ledger entry; do not chain them in one
sitting.

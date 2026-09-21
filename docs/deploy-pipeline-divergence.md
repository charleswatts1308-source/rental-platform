# How the two boxes came to deploy differently

**Status: LIVE.** The single place to understand why `gafol.rent` and
`renters.rent` do not deploy the same way, what the difference actually
is, what Hostinguk advised, and what is agreed. Written 21 Sep 2026.

**It corrects nothing.** `huk-laravel-site-install-recipe.md` is left
exactly as written, including a deployment note that no longer describes
gafol's behaviour — a record that gets quietly corrected stops being
evidence of what was believed at the time. Read the recipe for how the
sites were built; read this for what deploying does.

Related: `revision-2026-09-20-deploy-asymmetry.md` (the day-by-day
discovery), `environment-state.md` (the deployment ledger),
`NEXT-SESSION.md` open action 1a (the standing rule).

---

## 1. How we got here

The two environments were created at different times, by different
routes, for different reasons. Nothing was done wrong; they simply have
different histories, and one of those histories left out a step nobody
knew was significant.

**gafol.rent — permanent staging.** Created through **Laravel Toolkit's
own "Add application from Git" flow**. Toolkit therefore recorded the
repository as part of the application from the beginning.

**renters.rent — production.** Has a more complicated past. It originally
ran on a Windows Plesk box (master-branch lock, FTP deploys — long
superseded). It was **rebuilt as a new sibling site on the Linux
subscription, with DNS cut over on 4 July 2026**. In that rebuild the code
arrived through **the standard Plesk Git panel**, and the site was
registered with Laravel Toolkit as an *existing* application afterwards.

That ordering is the whole story. Confirmed by Hostinguk, 21 Sep 2026:

> gafol.rent's Laravel Toolkit application was created through Toolkit's
> own "Add application from Git" flow, so Toolkit knows the repository...
> renters.rent was deployed through the standard Plesk Git panel and
> registered as an existing application afterwards. Toolkit never recorded
> a repository for it.

**Toolkit knows about a repository only if it created the link itself.**
Registering an existing application does not backfill it.

## 2. What the difference actually is

### What deploying does

| | gafol.rent | renters.rent (PROD) |
|---|---|---|
| Maintenance mode on | yes | **no** |
| Deploy files | yes | yes |
| **Install Composer dependencies** | **yes** | **no** |
| Install Node.js dependencies | yes | **no** |
| Maintenance mode off | yes | **no** |
| Steps shown | six | one |

### What Laravel Toolkit shows

| | gafol.rent | renters.rent (PROD) |
|---|---|---|
| Registered as a Laravel app | yes | **yes** |
| Artisan / Composer / Node.js tabs | yes | yes |
| **Deployment tab** | **yes** | **no** |
| Repository shown | yes | **no** |
| Last commit shown | yes | **no** |

### What is IDENTICAL — so nobody chases the wrong thing

Both were checked on 20 Sep and are the same on each box:

- the Git panel settings: same remote, branch `main`, **Deployment mode
  Manual**, **"additional deployment actions" unchecked**;
- `composer --version` → **2.10.3** on both;
- both ARE registered Laravel applications in Toolkit.

Neither the Git settings nor a missing composer explains anything. The
difference is invisible on the screen most people would check first,
which is exactly why it took two rounds of "are you sure?" to find.

## 3. Why it matters

Production installs no dependencies on deploy. So **the first release that
adds a Composer package will work on staging and break production**: new
code running against an old `vendor/` directory, surfacing as fatal
"class not found" errors on whichever page touches the new library.

**Nothing in the deploy output would warn you.** From Plesk's point of
view on prod, the files copied and the job succeeded.

Every release to date has been safe only because none added a dependency.
That is luck, not design, and it is the reason this document exists.

## 4. What Hostinguk advised (21 Sep 2026)

**Q: Can an existing Toolkit application be linked to its repository?**
No supported option that they are aware of, and they would not guess on a
live production site.

**Q: Can the pipeline be reproduced without overwriting files, `.env`,
`storage/` or the database?**
Yes — by a different route. Use the **Git panel's "additional deployment
actions"** instead of the Toolkit link. Git writes only tracked files, so
`.env`, `storage/` and the database are untouched, **provided `.env` and
`storage/` are not tracked in the repository**.

**Q: What if the repository is recreated from Toolkit?**
They cannot say how Toolkit treats a non-empty document root — it may
refuse, merge or overwrite. **Not to be attempted on production.** Test on
a staging subdomain or a copy first if the Toolkit link is ever wanted.

**Q: Is it reversible? Maintenance window?**
Deployment actions are fully reversible: unticking the option restores
today's "Deploying files" behaviour. Take a **full backup**, files and
database, first.

**Q: What creates the link on future sites?**
**Create the application via Laravel Toolkit's "Add application from Git"
flow from the start**, rather than deploying through the Git panel first.

## 5. Their proviso — verified, not assumed

Checked against the repository on 21 Sep 2026:

- **`.env` is NOT tracked** — only `.env.example` is;
- **`storage/` holds nothing but Laravel's standard `.gitignore`
  placeholders** — no logs, no uploads, no framework cache; case
  attachments live under ignored paths;
- **`vendor/` is NOT tracked.**

A deploy can therefore only rewrite a handful of placeholder files with
identical content. The condition for the safe route is met.

## 6. What is agreed

**Now, and until the actions box is set up:** a release that changes
`composer.json` or `composer.lock` is **not finished on prod** until
`install --no-dev --optimize-autoloader` has been run from prod's Laravel
Toolkit **Composer tab**. Record it in that deploy's ledger entry. gafol
needs nothing — it does this itself.

**When the work is done, in this order:**

1. Full backup — files and database.
2. In prod's Toolkit terminal: `command -v composer` and `command -v php`.
   The actions box runs with a thinner environment than the terminal, so
   bare command names may not resolve. **Full paths, or it fails on the
   day.** This is the "yak" the install recipe warns about.
3. Fill the actions box: composer install, then the cache commands.
4. Test with a docs-only commit; watch the progress window; confirm the
   site is up.
5. Rollback if anything looks wrong: untick the box.

**Deliberately excluded: maintenance mode.** gafol gets it safely because
Toolkit owns the sequence and brings the site back up. Hand-rolled, a
failure partway through can leave production **down**, with nothing
scheduled to lift it. Deploys take seconds; the exposure is not worth it.

**For any future site:** create the application through Toolkit's "Add
application from Git" flow from the start. One step, and it prevents this
entire document from being needed again.

## 7. The thing worth remembering

Charlie noticed the two boxes behaving differently and said so **twice**.
It was twice explained away — first as "you probably ran composer
yourself", then as "display only, benign". Both readings were wrong, and
the truth was a production risk sitting one dependency away from a live
failure.

He is the only person who watches those screens. When he says something
looks odd, that is data.

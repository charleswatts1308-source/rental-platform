# Revision — how the two boxes actually deploy (20 Sep 2026)

**Status: LIVE. Supersedes, but does NOT amend, the deployment notes in
`huk-laravel-site-install-recipe.md`.** That recipe is left exactly as
written. It records what was true and what was believed when the sibling
sites were built, and a silently corrected record stops being evidence of
either. Read the recipe for the build; read this for what deploying does
today.

---

## What was believed

The recipe's gotchas say:

> **Plesk Git deploy does NOT run composer.** Deploy actions are off by
> default (and left off deliberately — pathed composer commands in the
> actions box are their own yak). After any deploy that changes
> composer.lock, manually run composer install via the Laravel Toolkit
> Composer tab.

That was written as a fact about *Plesk*. It is actually a fact about
**one particular Plesk configuration**, and the two boxes do not share it.

## What is true, observed 20 Sep 2026

Charlie noticed different output between the boxes across two deploys and
said so twice. It was twice read as cosmetic — once as "you probably ran
composer yourself", once as "display only, benign". **Both readings were
wrong.** Screenshots settled it:

- **gafol.rent deploy:** maintenance mode on → deploy files → **install
  Composer dependencies** → **install Node.js dependencies** → maintenance
  mode off → complete. Six steps.
- **renters.rent (PROD) deploy:** "Deploying files". One step.

### The cause, and it is not what the Git panel shows

Both repositories are configured identically in the Plesk **Git** panel:
same remote, branch `main`, Deployment mode Manual, "additional deployment
actions" unchecked. `composer --version` answers 2.10.3 on both. Neither
the Git settings nor a missing composer explains anything.

The difference is in the **Laravel Toolkit**, and both sites ARE registered
there — prod has Artisan, Composer and Node.js tabs. What prod lacks is the
link between the two:

| | gafol | renters.rent (prod) |
|---|---|---|
| Deployment tab | **yes** | **no** |
| Repository shown | **yes** | **no** |
| Last commit shown | **yes** | **no** |

**gafol's Laravel application is linked to the Git repository; prod's is
not.** So on gafol a deploy goes THROUGH the Laravel application, which
knows what a Laravel project needs. On prod, Git drops files next to an
application that never learns a deploy happened.

Most likely why: gafol was built by the recipe, whose STEP 3 is "Laravel
Toolkit → Install Skeleton". Production was rebuilt as a new sibling site
in July after the Windows box, with code arriving by Git into a docroot,
so that link was never formed. Not confirmed, and not worth confirming —
what matters is the behaviour, which is.

## The risk

**A release that adds a Composer package will install cleanly on gafol and
break production.** New code, old `vendor/`, surfacing as fatal "class not
found" errors on whichever page touches the new library — and **nothing in
the deploy output says anything went wrong**, because from Plesk's point of
view the files copied fine.

Every release to date has been safe only because none added a dependency.
That is luck, not design.

## How it is handled, agreed 20 Sep

**Do not try to link the repository to the Laravel application on prod.**
That path wants to clone into the application root, and a tool laying down
a fresh working copy over a live site is a worse outcome than the problem.
If the boxes are ever to be made identical, it is a deliberate conversation
with Hostinguk support, out of hours — not a panel experiment.

**Instead: after any deploy that changes `composer.lock`, run
`install --no-dev --optimize-autoloader` from prod's Laravel Toolkit
COMPOSER TAB.** It already exists and already works. No terminal, no SSH,
no risk. Same panel as Artisan.

**Standing rule until something changes:** a release touching
`composer.json` or `composer.lock` is not deployed to prod without that
step. Say so in the ledger entry for that deploy.

## What would make this safe rather than remembered

A drift check — compare the packages named in `composer.lock` against what
is actually installed in `vendor/composer/installed.json`, and report the
difference. Run from the Artisan tab after a deploy, or surfaced in admin.

The point is the same one that runs through every defect found on 19 and
20 September: **the failure is silent.** Nothing tells you prod's
dependencies are stale until a user finds the page that needs them. A
check makes the box say so itself, which is worth more than a rule someone
has to remember six months from now.

Not built as at 20 Sep 2026.

---

# UPDATE — 21 Sep 2026: Hostinguk's reply, and the agreed route

Appended, not rewritten. Everything above is what was known on 20 Sep and
stands as the record of it.

## What HUK confirmed

**The diagnosis was right, and the cause is the creation order.**
gafol.rent's application was created through Toolkit's own **"Add
application from Git"** flow, so Toolkit knows the repository, shows the
Deployment tab and last commit, and runs the full pipeline. renters.rent
was deployed through the standard **Git panel** and registered as an
existing application afterwards, so Toolkit never recorded a repository
for it.

**There is no supported way to attach an existing repository to an
existing Toolkit application** — they are not aware of one and would not
guess on a live production site. **And they will not say what Toolkit does
to a non-empty document root** if the repository is recreated from there:
it may refuse, merge or overwrite. Not to be tried on production; test on
a staging subdomain or a copy first if the Toolkit link is ever wanted.

**For any FUTURE site: create the application via Laravel Toolkit's "Add
application from Git" flow from the start**, rather than deploying through
the Git panel first. That single step is what the install recipe did not
know to specify.

## The agreed route — deployment actions, not the Toolkit link

Reproduce the pipeline in the **Git panel's "additional deployment
actions"** box instead. Their reasoning, and it is sound:

- **Git only writes tracked files**, so `.env`, `storage/` and the
  database are untouched — *provided* `.env` and `storage/` are not
  tracked.
- **Fully reversible:** unticking the option returns the Git panel to
  today's "Deploying files" behaviour.
- Take a **full backup**, files and database, first.

### Their proviso, VERIFIED 21 Sep 2026

Checked against the repository rather than assumed:

- **`.env` is NOT tracked.** Only `.env.example` is.
- **`storage/` holds nothing but Laravel's standard `.gitignore`
  placeholder files** — no logs, no uploads, no framework cache. Case
  attachments live under ignored paths.
- **`vendor/` is NOT tracked.**

So a deploy can only ever rewrite a handful of placeholder files with
identical content. The condition is met.

## The job, in order, when it is done

1. **Full backup** — files and database.
2. **Find the real paths.** In prod's Toolkit terminal: `command -v
   composer` and `command -v php`. This is the only fiddly part, and it is
   the "yak" the install recipe warns about — the actions box runs with a
   limited environment, so bare `composer` and `php` may not resolve even
   though they work in the terminal. Full paths, or it fails on the day.
3. **Fill the actions box** with composer install plus the cache
   commands, using those paths.
4. **Test with a docs-only commit.** Watch the progress window; confirm
   the site is up afterwards.
5. **If anything looks wrong, untick the box.** That is the rollback.

**LEAVE MAINTENANCE MODE OUT.** gafol gets it safely because Toolkit
manages the whole sequence and brings the site back up. Hand-rolled in the
actions box, a failure partway through can leave production **down** with
nothing scheduled to lift it. Deploys take seconds; the exposure is not
worth the risk of a stuck maintenance page.

## Until that is done

The standing rule from 20 Sep still applies: a release changing
`composer.json` or `composer.lock` is not finished on prod until composer
is run from the Laravel Toolkit **Composer tab**, and the ledger entry for
that deploy says so.

---

**SUPERSEDED AS THE PLACE TO READ, 21 Sep 2026 — content left intact.**
`docs/deploy-pipeline-divergence.md` now carries the whole story in one
place: how the two environments came to differ, the differences in full,
what is identical, HUK's advice, and what is agreed. This file stays as
the day-by-day record of how it was discovered — including the two
readings that were wrong — because that is the part a tidy summary loses.

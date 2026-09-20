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

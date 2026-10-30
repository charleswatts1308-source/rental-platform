# D0 report — site identity (domain-change plan, Phase 1)

30 Oct 2026. Deliverable 0: survey and decisions only. **No code edited.**
Brief: `docs/domain-change-plan.md` (Phase 1, steps 1–6). Branch to be
`site-identity`, tag `pre-site-identity` on main.

## What the survey found

**`renters.rent` appears 114 times in 35 live files** (excluding docs,
vendor, content-archive, markdown):

| Area | Files | Hits | Note |
|---|---|---|---|
| Letter template seeder | 1 | 31 | `LetterTemplateSeeder` — the wording of every letter and notice |
| Data migrations seeding templates | 2 | 7 | `2026_09_05_…contact_failed…`, `2026_10_18_…welcome_registered…` |
| Views (live pages) | 9 | 19 | privacy 4, landlords 4, cookies 3, offline 3, errors/_shell 2, register 1, escalation-routes 1 |
| Mail classes | 4 | 5 | CaseNotice, ContactReply, ContactMessageReceived, InboundEnquiryForward |
| App code | 3 | 3 | RegisteredUserController (registration message), ServiceAddress + PropertyType (comments) |
| `dev:*` commands | 4 | 4 | seed `admin@renters.rent` — local only |
| Static `public/` | 4 | 9 | sitemap 5, manifest 2, robots 1, sw.js 1 (comment) |
| Config/env | 3 | 6 | `.env.example` 3, `phpunit.xml` 2, `kickoff.ps1` 1 |
| Tests | 9 | 37 | ForwardInboundEnquiryTest 20, ContactMessageNotificationTest 4, … |

**Brand word "Renters" / `APP_NAME`: 29 hits in 19 files**, mostly the
homepage (`welcome-4`), about, how-it-works, prs-scale, the mail layout
(`vendor/mail/*`, which already reads `config('app.name')`), and the logo
in `layouts/app.blade.php`. Unused homepages `welcome.blade.php` /
`welcome-2.blade.php` also hit; they should be checked for live routes
and deleted if dead, not converted.

**Host badge confirmed** at `layouts/app.blade.php:223–224`:
`@unless(app()->environment('production'))` … `{{ request()->getHost() }}`.
It reads the real host today. Phase 1 must leave it alone and add the
test the plan specifies.

## Two kinds of name in the text, not one

The letters use the **domain as the service's name**: "signed '{tenant},
via renters.rent'", "The renters.rent team", "sent through renters.rent".
Pages use "Renters" as the name and `renters.rent` as the address. After
the rename these must split cleanly into:

- `{site_name}`: "Dear Landlord". Who is speaking.
- `{site_domain}`: `dearlandlord.co.uk`. Where to go.
- Mail addresses (`landlord-enquiries@mg.…`, `privacy@mg.…`): from the
  existing **Mailgun** settings, not `SITE_DOMAIN`. The letter-1 footer's
  `landlord-enquiries@mg.renters.rent` (#62) is one of these.

Each of the ~40 template mentions has to be classified name / domain /
mail address by reading it in context. **That classification changes
what landlords read, so Charlie sees the before→after of every letter
line before it is built.**

## Conflict to resolve — templates in existing databases

The plan (step 3) says a migration moves seeded template text to tokens.
But CLAUDE.md-level practice (#61/#62, the 5 Oct lesson) is that
**template WORDING changes go through the admin editor**, because it
writes `letter_text_change_history`. A migration can't easily do that:
`edited_by_user_id` is a non-null FK to `users`. The one precedent
migration (`2026_10_18_130000_email_buttons_logo_green`) skipped history
explicitly *because it was styling, not wording*. This is wording.

Options:
- **(a) Seeder only, no data migration.** dll is a fresh install, so its
  templates come from the (tokenised) seeder. gafol and dev get the
  tokens through the admin editor, with history. Old prod (renters.rent)
  is being retired and needs nothing. **Recommended:** it keeps the
  editor-only rule intact, and only two boxes need hand edits.
- **(b) Migration that also writes history rows**, attributed to the
  first `is_admin` user. Automatic, but a migration authoring
  "edits" in an admin's name is the kind of record that is hard to
  explain later.
- **(c) Compose-time fallback**: replace literal `renters.rent` in
  template text at send time. Rejected: invisible substitution in an
  evidential letter, and it hides drift.

## Static files

Recommend **serving `robots.txt`, `sitemap.xml` and `manifest.json`
from routes** so they read the settings, which takes them off the
hand-edit checklist. Small and testable. `sw.js` has a comment only.

## Out of Phase 1, by design

- **The logo** (inline SVG "Renters" + "STRENGTH IN NUMBERS", fixed 200px):
  a design asset; "Dear Landlord" needs a new logo. Charlie's call.
- `favicon.svg`, app icons: same.
- Archived content, history docs: untouched (standing rules).

## Decisions needed from Charlie before build

1. Template route: (a) seeder + admin editor on gafol/dev, or (b)?
2. In letters, should the service sign as the **name** ("via Dear
   Landlord") or the **domain** ("via dearlandlord.co.uk")? Today it is
   the domain. Content decision; shown line by line before build.
3. Route the three static files: yes / no.
4. OK to delete `welcome.blade.php` / `welcome-2.blade.php` if they prove
   unrouted?

## Rulings, 30 Oct 2026 (Charlie: "I'll go with your recommends")

1. **(a)**: tokenised seeder for fresh installs (dll); gafol and dev
   updated through the admin editor (history kept); no template data
   migration.
2. **Name where it says who is speaking** ("via Dear Landlord", "The
   Dear Landlord team"); **domain only where it says where to go**.
   Accepted 30 Oct. **D0 ACCEPTED.**
3. **Yes**: robots, sitemap and manifest served from routes.
4. **Yes**: delete `welcome` / `welcome-2` views if unrouted.

Also confirmed: domain and business name are settings, never hard-coded,
except the logo (design asset), letters already sent (frozen), things
outside the code, and history/archive docs.

## Test approach

Suite stays green; no assertion weakened. Tests that hard-code
`renters.rent` switch to asserting the **resolved value from config**,
which is at least as strong. New: host-badge test (real host shown in
non-production even when `SITE_DOMAIN` differs; absent in production);
fail-loud test (blank `SITE_NAME`/`SITE_DOMAIN` stops system mail, like
the Mailgun keys).

# Domain and business-name change — plan

Living doc. Started 25 Oct 2026. Status: **planning — new name not yet chosen.**

## Decisions so far (Charlie, 24–25 Oct 2026)

- The domain will change, tied to the business name being registered.
- **No live data to preserve.** Nothing on prod is real yet and there are no
  open cases, so this is a fresh install, not a migration. No old-domain mail
  to keep receiving, no sent links to protect, no frozen letters to worry about.
- **Build a new Plesk site** for the new domain, via Laravel Toolkit's
  **"Add application from Git"** flow. This gives prod the repository view
  gafol has and fixes the same-root-cause problems on prod (no composer
  install on deploy, scheduler oddity) — see `deploy-pipeline-divergence.md`.
- **Two settings, not one:** the **domain** and the **business name**, each
  set once per environment. Wherever the code or content needs them, it reads
  the setting.

## The two settings

| Setting       | Example today  | Used for                                        |
|---------------|----------------|-------------------------------------------------|
| `SITE_DOMAIN` | `renters.rent` | "emails come from an address ending …", links, sender display names |
| `SITE_NAME`   | `Renters`      | footer ©, page titles, "who we are", letters     |

- Live in a new `config/site.php`, read as `config('site.domain')` /
  `config('site.name')`.
- **No defaults**, same rule as the Mailgun keys (CLAUDE.md): an environment
  that forgets them must fail loudly, never fall back to a real-looking value.
  `CaseNotice` (and other system mail) should refuse to send if either is blank.
- `APP_NAME` can be set from it in `.env` (`APP_NAME="${SITE_NAME}"`) so
  Laravel's own mail layout and titles follow without a third setting.
- Mail addresses (`cases@mg.…`, `privacy@mg.…`, `inbox.…`) stay on the existing
  Mailgun settings (`MAILGUN_DOMAIN`, `MAILGUN_CASES_FROM_ADDRESS`,
  `MAILGUN_INBOUND_DOMAIN`). They change with the domain but are already
  settings — no new work beyond new values.
- **Letter templates** (database text, editable in admin) get tokens —
  `{site_name}`, `{site_domain}` — filled in when a letter is composed. The
  letter frozen on `case_messages` at send time holds the resolved text, never
  the token, so the evidential invariant is untouched.

**gafol uses the brand domain** for `SITE_DOMAIN` (Charlie, 25 Oct 2026), so
staging copy previews prod exactly.

**The host badge must survive — Charlie: "vital for knowing which site is
being tested".** The orange badge in `layouts/app.blade.php` (non-production
only) shows the box's **real host** via `request()->getHost()`. It must keep
reading the real host and **must NOT be converted to `SITE_DOMAIN`** — on
gafol that would print the brand domain and the badge would stop telling
staging from prod. No test protects it today; phase 1 adds one.

## Where the values must still be hard-coded

These cannot read a setting, or are outside the code. Each needs a hand edit
(or an outside action) on every rename. Checklist for the switch:

**1. Static files in `public/`** — served as-is, cannot read config.
- `public/manifest.json` — `name`, `short_name` (phone-app install name).
- `public/sitemap.xml` — five `<loc>` URLs.
- `public/robots.txt` — `Sitemap:` URL.
- `public/sw.js` — comment only (cosmetic).
- Option to remove these from the list: serve sitemap, robots and manifest
  from routes so they read config. Small job; decide during phase 1.

**2. The logo** — `layouts/app.blade.php` inline SVG: the word "Renters" and
the "STRENGTH IN NUMBERS" tagline. The text *could* read `SITE_NAME`, but the
logo is a fixed 200px design — a name of different length needs redesign, so
treat it as a design asset. Also check `public/favicon.svg`.

**3. Per-environment config** — this is where the settings themselves live:
- `.env` on **dev**, **gafol** and **prod** (new site).
- `.env.example` (documentation values).
- `phpunit.xml` (test values — tests set their own).

**4. Outside the codebase**
- Domain registrar: register the new domain; DNS for web, `mg.`, `inbox.`.
- **Mailgun:** add the new sending domain (`mg.<new>`, EU region) and inbound
  (`inbox.<new>`), verify DNS, inbound route to the new site's webhook. A new
  domain has no sending reputation — trial volume is a natural warm-up.
- **Plesk:** new site, SSL, Toolkit app from Git, scheduler created in Toolkit.
- Google Search Console (if registered).
- **ICO registration** (Z229825X): update trading name / details.
- Anything printed or shared that shows the old address.

**5. Docs, notes and comments** — no runtime effect, update by hand:
`CLAUDE.md` (mail rules name `mg.renters.rent`), `docs/environment-state.md`,
`docs/NEXT-SESSION.md`, Claude memory, code comments (`ServiceAddress`,
`PropertyType`, `ContactReply`, `sw.js`, `kickoff.ps1`). History docs are
**not** corrected (standing rule) — they record the old name truthfully.

**6. Archived content** — `resources/views/content-archive/*` mention the old
name. Archive, not live; leave unless an archived page is ever restored.

## Steps

Phase 1 can be done **now**, before the name is chosen: with today's values
(`renters.rent` / `Renters`) it is a zero-visible-change refactor.

**Phase 1 — code (dev, branch `site-identity`, tag `pre-site-identity`)**
1. `config/site.php` with the two settings, no defaults; fail-loud check.
2. Replace the ~117 `renters.rent` mentions and brand "Renters" uses in live
   pages, mail classes and the registration message with the settings.
3. Letter templates: tokens, resolved at compose time; a new migration moves
   seeded template text to tokens (fresh installs run it too).
4. Decide on static files (route them, or keep on the checklist above).
5. Full suite green; tests assert the resolved values, not the tokens.
   **New test for the host badge:** renders with the request's real host in a
   non-production environment, even when `SITE_DOMAIN` differs; absent in
   production.
6. **STAGE (gafol.rent):** add both settings to `.env`, `config:cache`, deploy,
   walk pages + a sandbox send.

**Phase 2 — outside (Charlie)**
7. Register the domain and business name.
8. Mailgun: add `mg.<new>` + `inbox.<new>`, DNS records, verify.

**Phase 3 — new prod site**
9. **PROD (new domain):** Plesk → new site → Laravel Toolkit → **Add
   application from Git** (repo, branch `main`). New empty database.
10. `.env`: settings, Mailgun keys, `APP_URL`; `migrate --force` + seed via
    Toolkit; scheduler via Toolkit; SSL.
11. Live verification with **real sends** (CLAUDE.md mail rule): letter out,
    landlord reply back, contact + privacy forwarders. Ledger entry.

**Phase 4 — old domain**
12. **PROD (renters.rent):** permanent redirect to the new domain.
13. Retire `mg.renters.rent` / `inbox.renters.rent` in Mailgun.
14. **Keep renters.rent registered** for a year or two so nobody can buy it
    and impersonate the service to landlords who have seen the name.

**Phase 5 — paperwork**
15. ICO details; business name and address on Landlords + Privacy (closes
    snag #42); checklist sections 4–5 above.

## Estimate

About 2–3 working sessions, over a few days elapsed (mostly DNS and Mailgun
verification waits). Charlie's panel time roughly 1–2 hours across phases 2–4.
Phase 1 is about one session on its own.

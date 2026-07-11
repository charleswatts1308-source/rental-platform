# Environment state — deployment ledger

Human-readable mirror of what is deployed where. The DB `migrations`
table is the source of truth; this file is reconciled against
`php artisan migrate:status` at each deploy (CLAUDE.md "Deployment ledger").

**Reconcile status:** git tips verified (27 Jun 2026). **gafol** and
**dotrent** both at current `main` with the **registration gate deployed
and verified live** (27 Jun 2026). Staging-at-or-ahead holds. Remaining:
the DNS flip (Phase C) + prod retirement only.

---

## main (working line)
- `origin/main`: `0e0a4e0` (pushed 11 Jul 2026) — code tip `b165114` +
  the merged registration gate (`15ec602`), docs commits, and the 11 Jul
  content rework (`66ff6fe` + `0e0a4e0`: About Us + How It Works collapsed
  to single pages; The Law / The PRS / The Longer Term / Landlord Contact
  Service / Know Your Landlord archived to the dev-only content-archive).
  Tags on origin: `pre-registration-lock` = `a63ac4a`, `post-d16-phase5`
  = `cf2f5c9`.
- Local `main`: ahead of `origin/main` (unpushed) — carries the 11 Jul
  ledger commits, the 3 PWA app icons (`a6517a6`), and the PWA wiring
  merged via `--no-ff` (merge `cd29c0d`; branch `feature/pwa-wiring` also
  pushed to origin). Push held per the Git rule until asked. renters.rent
  (prod) does NOT yet carry the PWA.

## gafol — permanent staging (gafol.rent) — ✅ AT MAIN (Phase A green)
- Box: gafol.rent is the staging domain. DB `ukrenter_gafol_db` on
  mysql01. (The stale "ukrenters.rent / HUK" label was wrong —
  ukrenters.rent was a separate earlier site scheduled for deletion.)
- Now at: **current `main`** (incl. the registration gate `15ec602`) —
  deployed via Plesk Git pull + composer install + `config:cache`. Plesk
  repo `laravel_093fde` tracks the `main` branch. (Phase A was `859827b`;
  re-pulled to carry the reg gate, 27 Jun 2026 — code only, no new
  migrations since Phase A.)
- **Registration: OPEN.** `.env` `REGISTRATION_OPEN_TO_ALL=true`. Open
  path verified working (the gate didn't break it); the verification
  email hit the Mailgun **sandbox** authorised-recipient limit — expected
  per the Mail rule (staging = sandbox, outbound only), not a gate issue.
- Migrations: the D14/D15 set was already Ran; the **2 D16 admin
  migrations ran clean** this session —
  `2026_06_21_100000_create_letter_text_change_history_table`,
  `2026_06_21_100100_create_settings_change_hist_table`.
- Schema verified against MariaDB (Migrations rule): both new tables
  checked via `information_schema` — `created_at` is `datetime NOT NULL`
  with **BLANK extra** on both (NO implicit `ON UPDATE CURRENT_TIMESTAMP`)
  → **#18-clear**. Columns match D16 intent (A1 version-history shape;
  B3 settings-audit shape).
- Surfaces validated loading against live MariaDB:
  - **A** (template editor) — renders full template inventory,
    version-tracking framing present.
  - **B** (settings editor) — 7 settings load, B2 `apply_inflight` flag
    present, default **No**.
  - **C** (case oversight) — read-only, renders the 8 D14 live-fire cases
    across the full state spread (awaiting tenant review, abandoned,
    dormant, on hold, resolved, open).
- **Content deploy (11 Jul 2026):** pulled `main` → `0e0a4e0`;
  `route:clear` + `view:clear`. **Content-only, NO migrations.** Verified:
  `/about` new repair-notice copy; `/members/how-it-works` single
  four-section page; old `/members/repair-notices` +
  `/members/know-your-landlord` → 404; `/content-archive` → 404
  (local-only gate holds on staging).
- **PWA deploy + install test (11 Jul 2026):** gafol temporarily switched
  to deploy `feature/pwa-wiring` (3 app icons + manifest + service worker
  + offline page + `<head>` wiring); `route:clear` + `view:clear`.
  **Installable PWA verified on a real iPhone over HTTPS** — adds to home
  screen painlessly, launches standalone, offline page renders. Branch
  then merged to `main` (`--no-ff`, merge `cd29c0d`). **TODO: switch
  gafol's Plesk repo back to `main` and pull** so staging tracks the main
  line again (main now carries the PWA).
- Last verified: 11 Jul 2026.

## dotrent — production candidate (dotrent.net) — ✅ AT MAIN (Phase B green)
- Box: dotrent.net, the production candidate for the renters.rent flip.
  DB `ukrenter_dotrent_db` on mysql01. Deploy mechanism: **Plesk Laravel
  Toolkit** (no `.git` in docroot).
- Now at: **current `main`** (incl. the registration gate `15ec602`) —
  code via Toolkit + composer install + `config:cache`. (Phase B build
  was `859827b` via `migrate:fresh --force`, clean rebuild from files per
  the June ruling; re-deployed to carry the reg gate, 27 Jun 2026 — code
  only, no new migrations since the fresh build.)
- Migrations: **all 35 Ran, batch 1** (fresh build). The full silence
  model + D14/D15/D16 are on dotrent for the first time.
- Schema verified (Migrations rule): `cases` and `case_messages` both
  checked via `information_schema` — every timestamp column has **blank
  extra**, NO implicit `ON UPDATE CURRENT_TIMESTAMP`. **#18-clean.**
- Seed: `RepairCategorySeeder` (11) + `LetterTemplateSeeder` (11) +
  `SettingSeeder` (8, `apply_inflight=0`) run **explicitly by class**.
  NO `DatabaseSeeder`, NO Faker Test User, **empty cases table** — clean
  production-candidate shape.
- Admin: `admin@renters.rent`, id 1, `is_admin=1`, `email_verified_at`
  set; created with verification handled (the gafol `markEmailAsVerified`
  trap avoided). **Future-rebuild note:** admin = the `is_admin` flag,
  set manually post-create; the old "ID 13" rule is retired/stale.
- **Registration: LOCKED and VERIFIED.** `.env` set in Plesk:
  `REGISTRATION_OPEN_TO_ALL=false` + `REGISTRATION_ALLOWLIST=<2 real
  family emails>`; `config:cache` run. Lock verified working live
  (27 Jun 2026). **Required `.env` keys — a rebuild must re-add them:**
  code defaults `OPEN_TO_ALL` to false so a rebuild fails SAFE-CLOSED,
  but the allowlist would be EMPTY (nobody registers) until re-added.
  - **Go-live switch (the ONLY one, deliberate later step):** flip
    `REGISTRATION_OPEN_TO_ALL=true` + `php artisan config:cache`.
- **Mail config — B2 CLOSED (Sat 2026-07-04):** dotrent live `.env`
  confirmed to set `MAILGUN_INBOUND_DOMAIN` (= `mg.renters.rent`) and
  `MAILGUN_CASES_FROM_ADDRESS` (= `preprod@mg.renters.rent`). Verified
  via `config:show services.mailgun` on the box. Fail-loud `CaseNotice`
  guard therefore satisfied; **B3 firmed up.** (Secrets not recorded
  here by policy.)
- **ACTION — rotate Mailgun credentials (do now, 2026-07-04):**
  `config:show` printed `MAILGUN_SECRET` + `MAILGUN_WEBHOOK_SIGNING_KEY`
  in full in a chat session on 2026-07-04. These are live production
  credentials now in a transcript. Rotate both in the Mailgun dashboard,
  update dotrent `.env`, `config:clear`. Independent of the flip — do not
  defer to go-live. Signing-key exposure specifically weakens
  inbound-webhook authenticity until rotated.
- **DECISION PENDING — from-address wording:** `cases_from_address`
  currently reads `preprod@mg.renters.rent` — cosmetic (the From address
  landlords/tenants see). Decide whether to switch to a public-facing
  address (e.g. `cases@` or `notices@`) before real landlords see
  outbound mail.
- Surfaces validated against production MariaDB: **A** (11 templates),
  **B** (settings, B2=No), **C** (empty, clean).
- Last verified: 27 Jun 2026.
- Next: retirement, NOT a flip. The "DNS flip renters.rent → dotrent"
  plan is SUPERSEDED — renters.rent is being built as its own fresh
  sibling site (see the renters.rent entry). dotrent remains preprod /
  proven dry-run and is retired once renters.rent is proven live.

## prod — renters.rent (Windows, EOL)
- Git tip: UNKNOWN (not reconciled this session). Demo mode. **DNS for
  renters.rent was cut over to the new Linux sibling (`217.194.210.16`) on
  4 Jul 2026** — this box stops being authoritative for renters.rent as
  propagation completes.
- DB: `rentals` + old `file_attachments` still present until the
  `2026_05_24` drop migration runs (per project memory).
- **Retirement trigger:** the new renters.rent sibling site (see its
  entry) replaces this Windows box — DNS for renters.rent points at the
  new Linux sibling install, NOT at dotrent (cut over 4 Jul 2026). When
  renters.rent is proven live and this box is confirmed dark, record that
  as prod's LAST event here ("retired, replaced by renters.rent sibling
  build, <date>") and THEN strike this entry. Recorded before removal, so
  we can prove when the old box stopped being authoritative. End state
  once BOTH this box AND dotrent are retired: three live entries — gafol
  (staging), renters.rent (production), and main.

## renters.rent — production (NEW sibling build) — ⏳ IN PROGRESS
- Box: renters.rent, a NEW site on the LX (`ukrenters.rent`) Linux
  subscription — its own folder alongside `dotrent.net` and `gafol.rent`.
  Built FRESH from the rental-platform repo (Git deploy, `main`) rather
  than by flipping DNS onto the dotrent install. `dotrent.net` to be
  retired once renters.rent is proven. (This supersedes the earlier
  "flip renters.rent → dotrent" plan in `dotrent-deploy-plan.md`.)
- DB: **`ukrenter_renters_db`** (created this session) — **CLEAN:** 35
  migrations all batch 1, `cases=0`, `users=1` (admin), reference tables
  seeded (`RepairCategorySeeder` / `LetterTemplateSeeder` /
  `SettingSeeder`, by class). No test data, no old `rentals` schema. NOT
  to be confused with the dead `ukrenters_rent` (see below).
- Admin: created via recipe **Step 10b** (production tinker path, since
  `dev:reset` refuses on `APP_ENV=production`): `is_admin=1` +
  `email_verified_at` set via `forceFill` + `markEmailAsVerified`.
- **DNS — CUT OVER (4 Jul 2026):** renters.rent A records (apex + www)
  pointed at the Linux Plesk server **`217.194.210.16`** via the HUK
  customer-portal DNS (ns1/2/3 — NOT Plesk DNS). renters.rent now
  resolves to the new sibling site; the old Windows box is superseded
  (see the prod retirement trigger).
- **SSL — DONE (11 Jul 2026):** cert installed; `https://renters.rent/`
  serves live over HTTPS (confirmed by external fetch of `/about`). TLS
  1.0/1.1 disable + HSTS not separately confirmed here.
- **NOT yet done (hardening + cutover tail):** scheduler heartbeat
  (`schedule:run` cron — without it NO sweep runs, escalations never
  fire), Mailgun inbound route → live renters.rent URL, registration-gate
  `.env` keys (`OPEN_TO_ALL`/`ALLOWLIST`), one inbound round-trip verify.
  See `pre-flip-checklist.md`.
- Status: **build clean; cutover/hardening pending. IN PROGRESS.**
- **Content deploy (11 Jul 2026):** pulled `main` → `0e0a4e0`;
  `route:clear` + `view:clear`. **Content-only, NO migrations.** Verified:
  `/about` new repair-notice copy; `/members/how-it-works` single
  four-section page; old `/members/repair-notices` +
  `/members/know-your-landlord` → 404; `/content-archive` → 404
  (local-only gate holds on production). Prod confirmed serving the new
  content live over HTTPS (external `/about` fetch, 11 Jul 2026).
  Remaining hardening tail (scheduler heartbeat, Mailgun inbound, reg-gate
  `.env`) still pending — unaffected by this content deploy.
- Last verified: 11 Jul 2026.

## Dead database — ukrenters_rent (DELETE AFTER go-live)
- `ukrenters_rent` is an OLD leftover database, NOT used by any live
  site. Holds stale May test data (a manual test case to a personal
  Outlook address, `user_id=1`) + the old `rentals` schema.
- **Naming trap:** one/two characters from the LIVE renters DB
  `ukrenter_renters_db`. Do NOT confuse them. `ukrenters_rent` = dead;
  `ukrenter_renters_db` = live renters.rent (35 migrations, clean).
- On 2026-07-04 a phpMyAdmin session was left pointed at this dead DB
  during `SHOW CREATE` / cleanup / migration-count checks — the cause of
  a false "test data arrived in the build" scare. The live DB was clean
  throughout; the repo is clean (no seeder/migration/fixture creates that
  data). Nothing real was touched.
- **Action:** delete AFTER go-live. No destructive DB ops during cutover.

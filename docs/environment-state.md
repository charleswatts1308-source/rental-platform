# Environment state — deployment ledger

Human-readable mirror of what is deployed where. The DB `migrations`
table is the source of truth; this file is reconciled against
`php artisan migrate:status` at each deploy (CLAUDE.md "Deployment ledger").

**Reconcile status:** **dotrent retired 1 Aug 2026** (see its entry — the
record is kept, the box is gone). **gafol at `bd80e12`** (24 Aug).
**renters.rent at `02f1505`** (23 Aug — read off the Plesk Git panel and
CONFIRMED 24 Aug). This is the commit the capture-run teardown left prod
on when it redeployed `main`; the teardown never recorded it, and the
header carried release 1's `65540e1` for a day instead. `02f1505` is
itself a docs commit, so prod's **code** content is exactly `a70065b`.
Verified 24 Aug: `02f1505` contains `a70065b` and **`7bcab73` (#47)**, and
`02f1505..bd80e12` has **no code delta at all** — so **prod and gafol are
code-identical**, prod trailing main by documentation only.
**Staging-at-or-ahead HOLDS.** Live entries are **gafol, renters.rent,
main** — remaining housekeeping is recording the old Windows prod box's
retirement.

**⚠ Correction, 24 Aug — the gafol figure was wrong twice.** This header
said `b92b907`, was "corrected" on 23 Aug to `7507a72`, and both were
wrong: gafol carried **release 1** on 23 Aug and was tested on it. The
23 Aug correction reconciled the header against a **stale section of this
same file** rather than against the box — the exact inversion the
CLAUDE.md rule forbids, which says the DB and the box are the source of
truth and this file is their mirror. Proven on 24 Aug when
`migrate --force` on gafol returned **`Nothing to migrate`** for a 9 Aug
migration that a box sitting at 1 Aug code could not have run.

**Still not reconciled against `migrate:status` since 27 Jun.** Two
months overdue, and NOT cleared by the 24 Aug pull. Do it at the next
touch of any box, per the CLAUDE.md Deployment-ledger rule.

---

## main (working line)
- **`origin/main`: `bd80e12` (24 Aug 2026).** Local and origin are LEVEL;
  main is always pushed. (`7507a72` was the 1 Aug tip, recorded below.)
  The push moved origin on by 17 commits (`8955def..7507a72`), which had
  accumulated since the last push on 11 Jul.
- Tip is the `--no-ff` merge of `feature/admin-unverified-users`
  (`7507a72`): the 27 Jul UI usability pass (admin unverified-users list,
  nav split into Properties/Cases, post-verification welcome banner), the
  1 Aug mail identity audit docs, and **one code change** — email case
  normalisation on both auth paths (registration + profile update).
  Suite green pre- and post-merge: **550 passed, 2279 assertions.**
  **No migrations in the merge.**
- Also now on origin, having been local since 11 Jul: the 3 PWA app icons
  (`a6517a6`) and the PWA wiring merge (`cd29c0d`).
- Earlier history for reference: `0e0a4e0` (11 Jul) was code tip `b165114`
  + the merged registration gate (`15ec602`) + the 11 Jul content rework
  (About Us / How It Works collapsed to single pages; The Law, The PRS,
  The Longer Term, Landlord Contact Service, Know Your Landlord archived
  to the dev-only content-archive). `b92b907` (24 Jul) was the
  onboarding-nav merge.
- Tags on origin: `pre-registration-lock` = `a63ac4a`, `post-d16-phase5`
  = `cf2f5c9`.
- Deployment of this line: **gafol at `bd80e12`** (24 Aug);
  **renters.rent at `02f1505`** (23 Aug teardown redeploy, confirmed off
  Plesk 24 Aug). The two boxes are code-identical: `02f1505..bd80e12` is
  documentation only. The "not yet deployed
  anywhere" note that sat here was three deploys out of date; removed
  24 Aug.

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
  then merged to `main` (`--no-ff`, merge `cd29c0d`). **Plesk repo switched
  back to `main` — CONFIRMED 13 Jul 2026.** Staging tracks the main line
  again and sits at the same commit as prod (`133a103`). Staging-at-or-
  ahead holds; the stage-then-prod discipline has been kept throughout.
- **Onboarding + content deploy (24 Jul 2026):** pulled `main` → merge
  `b92b907` (`feature/onboarding-nav` via `--no-ff`; onboarding hub +
  dashboard rework, About/home content refresh, and the non-prod hostname
  badge). Plesk Git pull + `config:cache` (+ `view:clear`). **Code-only,
  NO new migrations.** Suite green pre-merge (547 passed, 2265 assertions).
  Verified: yellow `gafol.rent` badge renders next to the logo (server-
  side from the Host header, so it shows in the installed PWA with no
  address bar) — confirms "am I on gafol?" at a glance. Prod renders no
  badge (`@unless(app()->environment('production'))`). Staging-at-or-ahead
  of prod still holds.
- **Usability + mail-identity deploy (1 Aug 2026):** pulled `main` →
  `7507a72` (the `--no-ff` merge of `feature/admin-unverified-users`).
  Plesk Git pull + `config:cache` + `view:clear`. **Code-only, NO
  migrations** (verified: no `database/migrations` changes across
  `133a103..7507a72`). Suite green pre- and post-merge: **550 passed,
  2279 assertions** (was 547/2265; the 3 new tests are the email
  normalisation ones).
  Carries: the 27 Jul UI usability pass, and **one behavioural code
  change** — email case normalisation on registration and profile update
  (Breeze's `lowercase` rule VALIDATED for lowercase rather than applying
  it, so a capitalised address was rejected outright).
  Verified on the box by Charlie: **stage badge** (the yellow
  `gafol.rent` hostname badge — CONFIRM this is what "new stage icon"
  meant), **nav changes** (Properties / Cases split to two top-level
  items), **content changes**, and the **unverified-users list** on the
  admin users page.
  NOT separately confirmed on gafol: the capitalised-email registration
  path itself. Staging-at-or-ahead of prod holds.
- **Release 1 deploy (23 Aug 2026) — NOT RECORDED AT THE TIME; entered
  retrospectively on 24 Aug.** gafol was pulled to the **release-1 line**
  (`65540e1`, or an immediate ancestor of it — the exact commit was not
  captured, and that omission is itself the finding). Carried the
  attachment policy, the new public `/landlords` page, the nav change and
  the cases content line.
  **Migration `2026_08_09_120000_seed_attachments_first_notice_max_setting`
  ran here** — confirmed 24 Aug, when a re-run reported `Nothing to migrate`.
  **Tested thoroughly by Charlie**, and that testing is what produced snags
  **#49, #50, #51, #52, #53, #54, #57 and #58**. Release 1 was promoted to
  renters.rent the same day. Stage-then-prod discipline was kept in fact;
  the ledger simply failed to say so, and for a day the docs asserted gafol
  was three weeks behind prod when it was current with it.
- **Release 2 deploy (23 Aug 2026) — DEPLOYED BUT NOT EXERCISABLE HERE.**
  The delivery-event/webhook work was deployed to gafol before prod, so
  the stage-then-prod order was kept. It **could not be tested on gafol**,
  and that is structural, not an oversight: per the CLAUDE.md Mail rule
  staging runs the **Mailgun sandbox, outbound only — the sandbox cannot
  do inbound at all**. No webhook can be delivered to this box.
  (Exact commit/branch not captured at the time.)
  **STANDING CONSEQUENCE, decide before building #25:** the delivery-event
  receiver is by construction **untestable on gafol**. #25 cannot be
  staged the normal way. Either it gets a non-Mailgun test path
  (synthetic POSTs with a locally computed signature), or it is exercised
  on prod the way the capture run was. This needs settling at design time
  rather than discovering it at deploy time.
- **Pull to main (24 Aug 2026):** Plesk Git pull → **`bd80e12`**. Carries
  release 2's code (`a70065b`, the `case_message_id` custom variable) plus
  the 23 Aug docs. Checked against `7507a72..bd80e12` before the pull:
  **no `composer.json`/`composer.lock` change** (composer step skipped) and
  **no `config/` or `.env.example` change**.
  Artisan, via Laravel Toolkit:
  - `migrate --force` → **`INFO  Nothing to migrate.`** — expected, since
    the only pending migration had already run with release 1.
  - `config:cache` → `Configuration cached successfully.`
  - `view:clear` → `Compiled views cleared successfully.`
  **Pull only — nothing verified on the site for release 2 yet.** Release
  1's surfaces were verified on 23 Aug (above).
  **`attachments.first_notice_max` = 1 on gafol — CHECKED 24 Aug.** Worth
  checking because the migration returns early when the row exists, so
  this pull could not have reset a value that release-1 testing had
  raised. It had not been raised. gafol therefore matches prod, which is
  pinned at 1 by decision, and **#53 is NOT armed on either box** (#53:
  removing one of several staged photos removes them all — unreachable at
  ceiling 1). Re-check this before raising the ceiling anywhere.
- Last verified: 23 Aug 2026 (release 1 surfaces). Code at `bd80e12`, 24 Aug.

## dotrent — preprod (dotrent.net) — 🛑 RETIRED 1 Aug 2026

> **RETIRED 1 Aug 2026.** No longer a live environment. Everything below
> this banner is the HISTORICAL record of the box as it stood when it was
> running — kept because it was the proven production dry-run, and because
> the Phase B build sequence is the reference for a rebuild. Do not treat
> any "ACTION" or "PENDING" item below as outstanding; they died with the
> box. renters.rent is now the only environment on `mg.renters.rent`.
>
> Ledger effect: live entries are now **gafol, renters.rent, main** —
> the end state the housekeeping plan was aiming at, once the old Windows
> prod box's retirement is also recorded.
>
> NOT CAPTURED — worth filling in: what retirement physically meant here
> (Plesk subscription deleted? site disabled but files retained? DB
> `ukrenter_dotrent_db` dropped or kept? DNS for dotrent.net?). The date
> is recorded; the disposal detail is not.

### Historical record (as at 27 Jun 2026) — ✅ WAS AT MAIN (Phase B green)
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
- ~~**ACTION — rotate Mailgun credentials (do now, 2026-07-04):**~~
  **DONE 1 Aug 2026** — both keys rotated in the Mailgun dashboard
  (snagging-list #23, open 28 days). The exposed values from the
  2026-07-04 transcript are dead. No dotrent `.env` update was needed:
  the box was retired the same day, leaving renters.rent as the only
  consumer of `mg.renters.rent`.
- **DECISION PENDING — from-address wording:** `cases_from_address`
  currently reads `preprod@mg.renters.rent` — cosmetic (the From address
  landlords/tenants see). Decide whether to switch to a public-facing
  address (e.g. `cases@` or `notices@`) before real landlords see
  outbound mail.
- Surfaces validated against production MariaDB: **A** (11 templates),
  **B** (settings, B2=No), **C** (empty, clean).
- Last verified: 27 Jun 2026.
- Outcome: **retired 1 Aug 2026**, as planned — not flipped. The "DNS flip
  renters.rent → dotrent" plan was SUPERSEDED; renters.rent was built as
  its own fresh sibling site (see that entry). dotrent served as the
  preprod / proven dry-run and was retired once renters.rent was proven
  live, which is exactly the condition that was set for it.

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

## renters.rent — production (NEW sibling build) — ✅ LIVE (hardening green)
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
- **Now at: `7507a72`** (2 Aug 2026) — see the 2 Aug deploy entry below.
  Previously `133a103` (13 Jul), which carried the PWA merge (`cd29c0d`)
  and the 11 Jul content rework. **PWA live and tested on prod.**
- **HARDENING TAIL — CLOSED (13 Jul 2026).** All four items proven live:
  - **Scheduler heartbeat — CONFIRMED RUNNING.** Evidence: `silence_shadow_log`
    rows stamped `2026-07-13 06:15:02`, one per non-terminal case. The Plesk
    cron fires `schedule:run`, `silence:sweep` evaluates. This was the last
    untested link — without it no sweep runs and escalations never fire.
  - **Outbound mail — PROVEN.** Landlord letter 1 dispatched from prod
    (Sun 12 Jul, evening) and delivered. Sent inline by the web request
    (`SendCaseNotice`), so this proves the mail chain but NOT the cron.
  - **Mailgun inbound route — LIVE and PROVEN** with real landlord replies
    landing back on cases. Round-trip closed.
  - **Registration gate — LOCKED and VERIFIED (13 Jul).** `config:show app`
    on the prod box: `registration_open_to_all` = **false**,
    `registration_allowlist` populated with **5 family addresses** (not
    recorded here — PII, same policy as the dotrent entry). Front door is
    shut to the public; only allowlisted addresses can register.
    The earlier allowlist failure was root-caused to **duplicate `.env`
    keys** (within one file Laravel takes the LAST `KEY=`, silently
    ignoring earlier ones) — same trap now written into the install
    recipe, Step 8. This also retires the 4 Jul "verification email sends
    nothing, logs nothing" LIVE ISSUE.
- **Escalation ladder — UNDER TEST (in flight, 13 Jul).** Case 3 on prod,
  Surface B set to `escalation.interval_days=1` / `escalation.max_notices=2`
  and snapshotted onto the case (shadow log confirms `0/1 days`, not the
  14-day default — the settings change landed BEFORE clock start, which is
  what B2-off requires). Letter 1 out Sun evening; first escalation due at
  the **06:15 sweep on Tue 14 Jul**. Note the sweep is a daily batch and
  silence is floored to whole days, so a 1-day interval fires ~34h after
  the letter, not 24h — and because each sent letter restarts the clock a
  beat AFTER the sweep timestamp, later rungs drift one sweep. Expected as
  designed; not a defect.
- ~~**Still open: UNCONFIRMED Mailgun credential rotation**~~ — **DONE
  1 Aug 2026.** `MAILGUN_SECRET` + `MAILGUN_WEBHOOK_SIGNING_KEY` rotated
  after 28 days exposed (snag #23, closed). **Proven working 2 Aug** by a
  live landlord reply arriving on a case — inbound signature verification
  passes against the new key, which is the check that actually matters.

- **Usability + mail-identity deploy (2 Aug 2026):** pulled `main` →
  `7507a72`. Plesk Git pull + `config:cache` + `view:clear`. **Code-only,
  NO migrations** (`133a103..7507a72` touches no `database/migrations`).
  Suite green 550/2279. Prod jumped three weeks in one hop: it had never
  carried the 24 Jul onboarding merge (`b92b907`), so this deploy landed
  the onboarding hub + content refresh AND the 27 Jul usability pass AND
  the 1 Aug email normalisation together.
  **VERIFIED LIVE on prod (2 Aug):**
  - **Email case normalisation — PROVEN.** A mixed-case address was keyed
    in at registration, accepted, and stored fully lowercased. This is the
    release's only behavioural code change and gafol did not test it.
  - **Registration + verification complete** — created and verified
    timestamps both correct on the new account.
  - **Auth mail delivered** (exercises `MAIL_FROM_ADDRESS`).
  - **Contact Us reply delivered** — the ONLY code path that sends from
    the apex, so the only live test of the 1 Aug apex SPF record.
  - **Landlord letter outbound delivered** (`cases@mg.renters.rent`).
  - **Landlord reply inbound bound onto the case** — proves the rotated
    signing key end to end.
  **DEFECTS FOUND during the same run** (both recorded, neither blocking):
  snag #27 gained a worse failure mode — opening the verify link in a
  browser signed in as a DIFFERENT user gives a hard 403, not the /login
  redirect previously recorded; and new snag #37 — the post-verification
  welcome banner never fires, because `redirect()->intended()` discards
  the `?verified=1` fallback whenever a `url.intended` exists.
  **HEADER-LEVEL ALIGNMENT — CONFIRMED (2 Aug), two of four paths.**
  Read from received-message headers, not inferred from DNS. The 1 Aug DNS
  change is now proven ALIGNED, not merely not-broken.
  - **Landlord letter → Outlook** (`cases@mg.renters.rent`, tenant-reply
    letter through `CaseNotice`): `spf=pass` `smtp.mailfrom=mg.renters.rent`;
    `dkim=pass` `header.d=mg.renters.rent`; `dmarc=pass action=none`
    `header.from=mg.renters.rent`; `compauth=pass reason=100` (Microsoft
    passed it explicitly on DMARC, not on reputation); `SCL:1` `BCL:0` —
    classified not-spam, not-bulk. `Sender:` and `From:` MATCH, so no
    "on behalf of" on this path. `Reply-To` is the inbound token address
    and does not disturb alignment (DMARC ignores Reply-To).
    `X-Mailgun-Tag: production` — environment tagging confirmed live.
  - **Tenant notification → Gmail** (`noreply@mg.renters.rent`):
    `dkim=pass` on BOTH `d=mg.renters.rent` (selector `s1`) and
    `d=eu.mailgun.org`; `spf=pass`; `dmarc=pass`
    `header.from=mg.renters.rent`. SPF and DKIM each pass independently,
    so a landlord who auto-forwards still receives an authenticated letter
    (DKIM survives forwarding; SPF does not).
  - **DKIM selector is `s1`** on `mg.renters.rent`. Recorded because it is
    not one of Mailgun's guessable defaults and DNS alone will not reveal it.
  - Sending is **Mailgun EU** (`euw1.send.eu.mailgun.net`) — a distinct
    config axis from the US region if this is ever rebuilt from the recipe.
  **STILL NOT HEADER-CONFIRMED:** (a) the stage-1 opening notice — same
  mailable, same envelope, differs only in subject/body, so authentication
  cannot differ; not a real gap. (b) The Contact Us reply, the ONLY apex
  sender (#32). By relaxed DMARC alignment it SHOULD pass — `mg.renters.rent`
  and `renters.rent` share an organizational domain — but this is reasoning,
  not a reading. Confirm from headers before relying on it.
  **UNMEASURED:** every message header-checked so far carried NO
  attachments. Attachment-bearing letters (first send only, up to 6 files
  / 2MB each) have never been spam-scored. Relevant to any decision about
  stripping attachments from the first letter for deliverability — that
  premise is currently untested in either direction.
- Status: **LIVE. Build clean, hardening green, escalation ladder under
  test.** Cron + outbound + inbound all proven on the real box.
- **Content deploy (11 Jul 2026):** pulled `main` → `0e0a4e0`;
  `route:clear` + `view:clear`. **Content-only, NO migrations.** Verified:
  `/about` new repair-notice copy; `/members/how-it-works` single
  four-section page; old `/members/repair-notices` +
  `/members/know-your-landlord` → 404; `/content-archive` → 404
  (local-only gate holds on production). Prod confirmed serving the new
  content live over HTTPS (external `/about` fetch, 11 Jul 2026).
  (The hardening tail referenced here was subsequently CLOSED — see the
  13 Jul entries above.)
- **Attachment policy + landlords page deploy (23 Aug 2026):** pulled
  `main` → **`65540e1`**. Plesk Git pull, `migrate --force`,
  `config:cache`, `view:clear`.
  **ONE MIGRATION RAN, clean:**
  `2026_08_09_120000_seed_attachments_first_notice_max_setting` (2.29ms).
  It INSERTS a settings row only — no table created or altered — so the
  CLAUDE.md MariaDB `SHOW CREATE TABLE` rule does not bite here.
  `SettingSeeder` deliberately NOT run: it is `updateOrCreate` and would
  have reset every other tuned setting to its shipped default. The
  migration exists precisely to avoid that.
  Carries FOUR things, wider than the release title suggests:
  - **Attachment policy** — letter 1 may carry tenant photos under a new
    admin ceiling (0-3). **SHIPPED AT 1 BY DECISION**, not merely by
    default: ceiling 1 is the tested capability (snag #54), and #53
    (removing one of several staged photos removes them all) is
    unreachable at 1 and armed above it. **Do not raise the ceiling on
    prod until #53 is fixed.**
  - **New public page `/landlords`** + nav entry. Publishes ICO
    registration reference **Z229825X**, corrected this release (#55 —
    the previous value was the payment/account number, not the register
    reference, and had never been checked).
  - **Nav change** — Landlords moved to the end, "For" dropped.
  - **Cases content** — the create form now states what a blank landlord
    name does to the letter.
  Suite green pre-deploy: **570 passed, 2330 assertions** (22 Aug, at
  `ded69f2`; the only code change after that is the one-line ICO
  reference, which no test asserts).
  **KNOWN DEFECTS SHIPPED, accepted not overlooked:** #49 (the preview
  shows one landlord name and the letter sends another — needs a REPEAT
  landlord email to trigger, and affects all four letters, not just the
  first), #53, #54, #56. #49(b) was ruled fix-before-deploy on 22 Aug
  and that ruling was REVERSED 23 Aug to prioritise the delivery-failure
  work.
  **NOT YET VERIFIED ON THE BOX** — deploy recorded, verification
  outstanding: `/landlords` renders and shows Z229825X; nav order; admin
  photo ceiling reads 1; an attachment-bearing letter sends, arrives and
  opens; the 4-8MB band produces OUR file-named error rather than PHP's
  generic one; spam-scoring of an attachment-bearing letter (never done
  on any path).
  **PHP LIMITS (23 Aug):** `upload_max_filesize` 8M, `post_max_size` 16M
  — set in CloudLinux PHP Selector -> Options, which is
  **SUBSCRIPTION-WIDE** across all domains. There is no per-domain lever
  (Isolates does not work under LiteSpeed), and no `.user.ini` or
  `php.ini` override exists anywhere (filesystem search across the whole
  home directory, 23 Aug). So gafol and prod necessarily read the same.
  See install recipe STEP 1b.
  **RECONCILE STILL OUTSTANDING:** `php artisan migrate:status` was NOT
  run at this deploy. The ledger has not been reconciled against it
  since 27 Jun, contrary to the CLAUDE.md Deployment-ledger rule. Carry
  to the next deploy.
- **Delivery-event CAPTURE RUN (23 Aug 2026) — deployed, run, and TORN
  DOWN the same evening.** Temporary by design; prod is back on `main`.
  - **On:** Plesk repo pointed at `feature/delivery-capture` (main plus
    a write-only capture endpoint), `MAILGUN_CAPTURE_TOKEN` set in prod
    `.env`, `config:cache`. Domain-level Mailgun webhook on
    `mg.renters.rent` subscribed to Delivered / Permanent Fail /
    Temporary Fail / Complained. No migrations.
  - **Three real sends** from production cases **9RKDKC**, **3YHRKZ**,
    **CZPUAD** — a non-existent domain, a genuinely suppressed address,
    and a real Outlook inbox. All three events captured.
  - **Off:** webhook deleted FIRST, then token removed, `config:cache`,
    capture log deleted from the box, Plesk repo returned to `main` and
    redeployed. **VERIFIED:** GET on the capture path now returns 404
    where it returned 405 while deployed — the route is gone, not
    merely inert. Token burnt (it travelled in the URL and is in the
    access logs).
  - **The redeploy landed on `02f1505`** — established 24 Aug by reading
    the Plesk Git panel, NOT recorded at the time. "Returned to `main`
    and redeployed" did not say which commit that was, so the header
    carried release 1's `65540e1` for a day afterwards. That was
    demonstrably wrong before anyone looked: `65540e1` predates
    `a70065b`, yet the sends captured that evening carried
    `case_message_id`. Prod also gained **#47** (`7bcab73`) at this
    redeploy — confirmed, `7bcab73` is an ancestor of `02f1505` — `CaseStatus.php`,
    `CaseNotice.php`, `SilenceSweep.php`, `RepairCase.php` and
    `SilenceClock.php` all changed after `65540e1`. That is behavioural
    code, not documentation, and it went live unrecorded.
    **Consequence for planning:** #47 is ALREADY ON PROD. Anything that
    treats it as a pending prerequisite for #25 is reading a stale
    baseline.
  - **Findings are in `docs/mailgun-delivery-event-payloads.md`.**
    Headline: the event signature IS nested (so the existing middleware
    would 406 every event and Mailgun would never retry), payloads are
    JSON not form-encoded, there is no `permanent_fail` event —
    `failed` + `severity` — and our `case_message_id` custom variable
    survives the round trip, which is what makes the receiver possible.
  - **A config trap cost an hour and is worth remembering:** the
    endpoint 404'd repeatedly after the token was set, because the
    compiled `bootstrap/cache/config.php` was stale. `config:show` runs
    under the CLI and reported the new value happily while the web
    request used the cached one. `config:clear` fixed it; `config:cache`
    afterwards was then fine. Same CLI-versus-web split as the PHP
    limits (recipe STEP 1b).
  - **Still outstanding from this run:** abandon the three test cases,
    and run the suppression SQL in the release checklist against
    `charles.watts1308-t1@gmail.com` — a real July hard bounce, proven
    this evening to be still silently swallowing letters.
- Last verified: 13 Jul 2026 (the 23 Aug attachment deploy is recorded
  above but its verification list is outstanding; the capture run above
  is verified complete).

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

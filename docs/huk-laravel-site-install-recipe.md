HUK LARAVEL SITE — INSTALL RECIPE
=================================

Build a new Laravel site on HostingUK Plesk from scratch.

Derived from the dotrent.net build (May 2026). Reflects the corrected DNS
path discovered mid-build by comparing dotrent.net against the working
gafol.rent setup. Both sites use the same approach.

USE: tick "[x]" as you complete each step. Order matters for some steps,
not for others — sequence is correct as written.


PRE-CONDITIONS
==============

[ ] Domain registered with HUK (or registered elsewhere with NS pointing
    at HUK)
[ ] HUK subscription with Plesk access exists
[ ] You have the GitHub repo URL and SSH/HTTPS access set up


BACKGROUND — TWO DNS SYSTEMS AT HUK
====================================

HUK provides TWO separate DNS hosting systems. Knowing which is which
prevents the confusion this recipe was nearly wrecked by:

1. **HUK customer-portal DNS** — uses nameservers ns1/2/3.hostinguk.net.
   Managed via HUK's customer portal "DNS Management" page. Minimal records
   (A, MX). You add records by hand. This is what gafol.rent uses, and what
   this recipe uses for dotrent.net. THE RECOMMENDED PATH for HUK sites.

2. **Plesk DNS** — uses nameservers ns10/11/12.hostinguk.net. Managed via
   the Plesk site's DNS Settings page. Auto-creates a comprehensive set
   (SPF, DKIM, DMARC, MX, SRV, CNAME — ~22 records). Plesk's "Domain not
   resolvable" warning banner tells you to use these. DO NOT FOLLOW THAT
   ADVICE without thought (see below).

**Why use customer-portal DNS (option 1)?**

- It's what gafol.rent and other working sites use — proven pattern
- Plesk DNS creates a dual-zone trap: if you don't fully cut over to ns10/11/12,
  the customer-portal zone still exists alongside, holding different records,
  causing inconsistent resolution
- For Mailgun sandbox-based testing (the LLCS pattern), you don't need the
  auto-managed SPF/DKIM/DMARC that Plesk creates — those only matter when
  sending mail FROM your own domain
- Simpler — one zone, hand-managed, no surprises

**When would Plesk DNS be the right choice?**

If you intend to send transactional mail FROM your own custom domain (e.g.
mg.yoursite.com with Mailgun verifying your own DNS), Plesk's auto-generated
SPF/DKIM/DMARC is convenient. But these can also be added manually to the
customer-portal DNS in a few minutes.

Long-term best practice (not done here): move all domains to Cloudflare DNS
for free, with a much better UI, faster propagation, and host independence.

**Conclusion: this recipe uses customer-portal DNS (option 1).**


STEP 1 — ADD DOMAIN TO PLESK
============================

[ ] Plesk → Websites & Domains → Add Domain (or add to existing subscription)
[ ] Enter domain name
[ ] Plesk creates its own DNS zone for the domain — IGNORE IT, we're using
    customer-portal DNS instead
[ ] Plesk will show "Domain not resolvable" warning — IGNORE the prompt to
    switch to ns10/11/12. The warning will persist; just dismiss it.


STEP 2 — CONFIGURE CUSTOMER-PORTAL DNS
======================================

Do this NOW so propagation runs in parallel with the rest of the build.

[ ] HUK customer portal → My Domains → click domain
[ ] Nameservers → confirm "Use default nameservers" is selected
    (ns1/2/3.hostinguk.net) — this is the customer-portal DNS path
[ ] DNS Management → check the A records:
    - @ (apex) should point at Plesk server IP (currently 217.194.210.16)
    - www should point at the same IP
[ ] If the A records show a different IP (e.g. 217.194.210.198 — a HUK
    parking/default IP):
    - Edit both A records to the correct Plesk server IP
    - Save Changes
[ ] MX records can stay as HUK defaults (smtp01.hostinguk.net, mail.hostinguk.net)
    — they handle inbound mail to the apex domain if anyone sends there

(Verification happens at Step 11. Plesk's warning toast will remain visible
since we're not using Plesk DNS — that's expected and harmless.)

**Important:** the Plesk DNS zone for this domain still exists, dormant.
DON'T edit it. The customer-portal DNS at ns1/2/3 is now authoritative.


STEP 3 — INSTALL LARAVEL SKELETON
=================================

[ ] Plesk → site → Laravel Toolkit → Install Skeleton
[ ] Wait for completion
[ ] At this point the site has a working Laravel install with default .env
[ ] Note: skeleton install does NOT create a database — that's Step 4


STEP 4 — CREATE DATABASE
========================

[ ] Plesk → site → Databases tab → Add Database
[ ] Database name: choose a convention (e.g. <sitename>_db)
    Plesk prepends a subscription prefix — note the full final name
    (e.g. asking for "dotrent_db" gives "ukrenter_dotrent_db" if the
    subscription is under ukrenters.rent)
[ ] Database user: create new
[ ] Password: let Plesk generate, SAVE TO 1PASSWORD IMMEDIATELY
[ ] Note the database host (e.g. mysql01.hostinguk.net) — visible in
    the database details page after creation


STEP 5 — CONNECT GITHUB REPO
============================

[ ] Plesk → site → Git extension (separate icon, not part of Laravel Toolkit)
[ ] Add Repository
[ ] Remote URL: the GitHub HTTPS URL
[ ] Branch: main
[ ] Deploy location: site root
[ ] Trigger initial deploy (Pull + Deploy)
[ ] Repo files now replace the skeleton install


STEP 6 — CREATE STORAGE DIRECTORIES (CRITICAL)
==============================================

After a Git deploy, Laravel's storage subdirectories may be missing because
they're typically gitignored. If any are missing, the site will throw a 500
"Please provide a valid cache path" error on first load.

Via Plesk File Manager OR the Toolkit Terminal:

[ ] cd /var/www/vhosts/<subscription>/<sitename>
[ ] Create all of these (with -p to suppress errors if already exist):
    mkdir -p storage/framework/cache/data
    mkdir -p storage/framework/sessions
    mkdir -p storage/framework/testing
    mkdir -p storage/framework/views
    mkdir -p storage/logs
[ ] Set permissions to allow web server to write:
    chmod -R 775 storage bootstrap/cache

Without these, no Blade template can render — every page returns 500.


STEP 7 — INSTALL COMPOSER DEPENDENCIES
======================================

[ ] Plesk → site → Laravel Toolkit → Composer tab
[ ] Run: composer install
[ ] (Use composer install WITH dev deps so dev:lifecycle and other
    development tooling works — production-style --no-dev can come later)


STEP 8 — CONFIGURE .env
=======================

Easiest method: copy .env from an existing working site (e.g. gafol.rent),
paste into the new site's .env editor, then edit only the fields that differ.

[ ] Open .env editor in Laravel Toolkit
[ ] If starting from .env.example: rename to .env first
[ ] If copying from another site: paste full content from source site's .env,
    then edit only the fields below

Fields that MUST be updated for the new site:
[ ] APP_NAME="Site name (env)"
[ ] APP_ENV=preprod  (or staging / production as appropriate)
[ ] APP_KEY=         (BLANK — will be regenerated in Step 9)
[ ] APP_URL=https://newsite.tld
[ ] DB_DATABASE=<from Step 4>
[ ] DB_USERNAME=<from Step 4>
[ ] DB_PASSWORD=<from Step 4>
[ ] DB_HOST=<from Step 4, e.g. mysql01.hostinguk.net>
[ ] MAILGUN_CASES_FROM_ADDRESS=<envtag>@<mailgun-domain>
    (e.g. preprod@sandbox...mailgun.org for preprod,
     sandbox@... for staging, cases@mg.renters.rent for production)

Fields to verify (should be inherited correctly if copied from working site):
[ ] MAIL_MAILER=mailgun
[ ] MAILGUN_DOMAIN=<sandbox or mg.renters.rent>
[ ] MAILGUN_SECRET=<sandbox or production key>
[ ] MAILGUN_ENDPOINT=api.mailgun.net (sandbox in US) or
    api.eu.mailgun.net (production EU)
[ ] MAILGUN_WEBHOOK_SIGNING_KEY=<from Mailgun dashboard>
[ ] MAILGUN_INBOUND_DOMAIN=<same as MAILGUN_DOMAIN per KISS policy>

Critical: BLANK APP_KEY before saving. Each site must have a unique key.
Save.

[ ] DEV_TENANT_EMAIL / DEV_LANDLORD_EMAIL — required by dev:lifecycle
    since commit ede2cbb (real, sandbox-authorized addresses; the
    Gmail plus-addresses). Staging/preprod only.

STEP 9 — GENERATE APP_KEY
=========================

[ ] Laravel Toolkit → Artisan tab → run: key:generate
[ ] Verify: Artisan tab → run: config:show app
[ ] Confirm: name, env, url, debug=OFF all match the .env values


STEP 10 — RUN MIGRATIONS
========================

[ ] BEFORE migrating, CONFIRM you are on the intended NEW database:
    Artisan tab → run: db:show   (or: config:show database)
    Verify the database name is EXACTLY the one created in Step 4
    (e.g. ukrenter_renters_db) — NOT an old/near-identically-named DB.
    The copied-.env trap (Step 8) plus HUK's near-identical names
    (e.g. ukrenters_rent vs ukrenter_renters_db) can leave you
    inspecting or operating the WRONG database. Migrating or cleaning the
    wrong DB is silent and destructive. One character's difference.
[ ] Laravel Toolkit → Artisan tab → run: migrate --force
[ ] Verify 18+ migrations run cleanly (no errors)
[ ] Verify the schema in phpMyAdmin if you want extra certainty


STEP 10b — CREATE ADMIN USER (PRODUCTION ONLY)
==============================================

On staging/preprod the admin is seeded by dev:reset / dev:lifecycle
(see Step 14). Those dev:* commands REFUSE to run on APP_ENV=production
(gated to local/staging/preprod), so a PRODUCTION build must create the
admin by hand. Do it here — migrations (Step 10) and APP_KEY (Step 9)
must already be done.

Two user columns are deliberately NOT mass-assignable (privilege/security
boundaries), so a plain create() cannot set them — they must be set
explicitly, or you get the trap hit on gafol/dotrent:

  - is_admin          — admin access IS this flag (AdminMiddleware checks
                        Auth::user()?->is_admin). The old "ID 13" rule is
                        retired/stale.
  - email_verified_at — without it the admin cannot clear the `verified`
                        middleware on the admin routes and is locked out
                        behind the email-verification wall.

[ ] Laravel Toolkit → Artisan tab → Tinker. Use ONE strong password,
    straight to 1Password — DO NOT paste it into any chat, log, or ticket:

    $u = App\Models\User::create([
        'name' => 'Admin',
        'email' => 'admin@renters.rent',
        'password' => Illuminate\Support\Facades\Hash::make('<STRONG-PASSWORD>'),
    ]);
    $u->forceFill(['is_admin' => true])->save();
    $u->markEmailAsVerified();

    (This is exactly what dev:reset does — forceFill(is_admin) +
    markEmailAsVerified — minus the table wipe.)

[ ] Verify in Tinker:
    App\Models\User::first()->only(['id','email','is_admin','email_verified_at'])
    Expect is_admin => 1 (true) and a non-null email_verified_at.
[ ] Log in at https://<domain> as admin@renters.rent and confirm the admin
    area loads — proves both the flag and the verification took.


STEP 11 — ARTISAN FINAL TASKS
=============================

[ ] Artisan tab → run: storage:link
    (Links public/storage → storage/app/public for file serving)
[ ] Artisan tab → run: about
    Verify everything looks right:
    - Environment matches APP_ENV
    - Debug = OFF
    - Mail = mailgun
    - Database = mysql
    - URL matches the domain


STEP 12 — VERIFY DNS PROPAGATION
================================

DNS work was kicked off in Step 2. By now propagation should be complete
or close to it.

[ ] From dev PC, verify nameservers and A record via Cloudflare:
    nslookup -type=NS <domain> 1.1.1.1
    Should return ns1/2/3.hostinguk.net (customer-portal DNS)
    nslookup <domain> 1.1.1.1
    Should return the Plesk server's IP (e.g. 217.194.210.16)


STEP 13 — VERIFY IN BROWSER
===========================

[ ] Visit https://<domain>
[ ] If browser shows NXDOMAIN / "server failed" / wrong IP:
    The local resolver (your home router) may still have stale cache.
    Options:
    - Restart router and retry (may not work if router holds cache aggressively)
    - Change PC DNS to 1.1.1.1 directly:
      Windows Settings → Network → active connection → DNS server assignment
      → Manual → IPv4 ON → Preferred: 1.1.1.1, Alternate: 1.0.0.1
      Then: ipconfig /flushdns
    - Edge/Chrome: chrome://net-internals/#dns → Clear host cache
[ ] Site should load the application's homepage


STEP 14 — SEED DEMO DATA (PREPROD/STAGING ONLY)
================================================

Skip this step for production.

[ ] Artisan tab → run: dev:lifecycle
[ ] Verify 8 cases created, one per status
[ ] Login as a tenant (tenant1@example.test / password) to confirm UI works


STEP 15 — TEST OUTBOUND MAIL
============================

[ ] Identify a Mailgun authorized recipient on the sandbox
    (e.g. your real Gmail)
[ ] In phpMyAdmin, update case 1's landlord email to that recipient
[ ] Artisan tab → run: dev:letter --case=1 --stage=1
[ ] Verify clean exit (no exception, no 403)
[ ] Check Mailgun sandbox log: message should show as Delivered,
    tagged with APP_ENV value (e.g. "preprod")
[ ] Check recipient's Gmail (may be in Spam due to DMARC misalignment
    on sandbox — expected, not a bug)


COMMON GOTCHAS
==============

- **Plesk's "Domain not resolvable" warning** is shown if you're not using
  Plesk DNS. This recipe deliberately uses customer-portal DNS, so the
  warning is expected. Ignore (or dismiss) it. The warning is not a
  symptom of a broken setup — it's just Plesk wanting you to use its DNS.

- **DON'T switch nameservers to ns10/11/12** based on Plesk's banner
  unless you commit to fully using Plesk DNS (and accepting its trade-offs
  around dual zones). Switching back and forth creates leftover dormant
  zones holding stale records.

- **Cache table is auto-created by the default migration set** (the 0001
  cache migration creates both `cache` and `cache_locks`).

- **Sessions table is auto-created by Laravel** on first request when
  SESSION_DRIVER=database. Not via migration — it just appears. Not a
  bug, it's a framework feature.

- **Storage subdirectories must exist for Laravel to render any page.**
  Git typically excludes them. If you see "Please provide a valid cache
  path" or "Failed opening required" 500 errors, Step 6 is the fix.

- **After ANY .env change, run config:clear** (the Laravel Toolkit .env
  editor sometimes does this automatically, sometimes doesn't).

- **HUK's subscription prefix is added to database name AND username** —
  e.g. you ask for "dotrent_db" and get "ukrenter_dotrent_db" (because
  the subscription is under ukrenters). Note the full final name.

- **NEVER run bare `db:seed` (or `migrate --seed`) on production.** The
  default DatabaseSeeder creates a `Test User` (test@example.com) via
  factory. On production seed ONLY the reference seeders by class:
  `db:seed --class=RepairCategorySeeder` (and LetterTemplate, Setting).

- **Near-identical DB names are a real trap.** `ukrenters_rent` (an old
  dead database) vs `ukrenter_renters_db` (the live renters.rent DB)
  differ by one/two characters. A phpMyAdmin session or copied-.env left
  pointing at the wrong one makes cleanup/inspection hit a dead database
  while the real one looks untouched (or vice versa). Always confirm the
  DB name (Step 10 check) before any SHOW CREATE, cleanup, or migrate.

- **composer install vs composer install --no-dev**: use WITH dev deps for
  staging/preprod (so dev:lifecycle and tooling work). Use --no-dev for
  production (smaller footprint, no test dependencies).

- **Local router DNS cache is the most common reason "the site doesn't
  load."** Public DNS via Cloudflare being correct (`nslookup <domain>
  1.1.1.1`) is the truth. If your browser disagrees, the problem is
  between your PC and the router, not in the actual DNS setup. Setting
  PC DNS to 1.1.1.1 is a permanent fix worth doing once.

- **Plesk Git deploy does NOT run composer.** Deploy actions are off by
  default (and left off deliberately — pathed composer commands in the
  actions box are their own yak). After any deploy that changes
  composer.lock, manually run composer install via the Laravel Toolkit
  Composer tab (plain install WITH dev deps on staging/preprod). Symptom
  of forgetting: "Call to a member function unique() on null" from a
  Factory — Faker (a dev dependency) is missing from vendor/.

- **Mailgun sandbox authorized recipients must be VERIFIED, not just
  added.** Adding an address triggers a confirmation email; until the
  link is clicked the sandbox 403s sends to it ("Free accounts are for
  test purposes only"). Both DEV_TENANT_EMAIL and DEV_LANDLORD_EMAIL
  addresses need adding AND confirming on the sandbox before
  dev:lifecycle will run on a sandbox-configured site.

- **Sandbox→Gmail delivery degrades with use.** Gmail progressively
  spam-blocks the shared sandbox domain (550 5.7.1) — bursts of similar
  mail make it worse, and even "Delivered" mail lands in Spam
  (DMARC:Quarantine). Expect partial delivery on sandbox tests; the
  Mailgun Logs page is the ground truth of sent vs delivered vs failed.
  This is sandbox reputation, not an app bug — production's
  DMARC-aligned domain doesn't have it. Timestamp gotcha: Mailgun logs
  display Europe/London; DB sent_at is UTC — one hour apart in summer.

- **dev:lifecycle truncates and reseeds case data on every run, but
  Gmail keeps everything.** When reconciling sends against an inbox,
  match on case REFERENCE, not subject/address — the inbox accumulates
  debris from prior runs that no longer exists in the DB. For a clean
  reconciliation test: empty the Gmail folders first, run once, compare.




SITE INVENTORY (for reference)
==============================

renters.rent     — production target. Still on original Windows HUK.
                   Awaiting cutover to Linux.
gafol.rent       — staging. Clean Linux Laravel build on customer-portal
                   DNS (ns1/2/3).
dotrent.net      — preprod (built using this recipe, May 2026).
                   Customer-portal DNS. Sandbox-config dry run for
                   production cutover.
ukrenters.rent   — earliest Linux site, hand-built during HUK ticket
                   period. Subscription host for gafol.rent and
                   dotrent.net. Scheduled for eventual deletion.


WARNING ON DELETING ukrenters.rent
==================================

gafol.rent and dotrent.net are nested INSIDE the ukrenters.rent
vhost directory on Plesk's filesystem
(/var/www/vhosts/ukrenters.rent/<sitename>/).

If ukrenters.rent is deleted by removing the whole
vhosts/ukrenters.rent/ tree, the nested sites will be deleted too.

Use Plesk's UI to delete the ukrenters.rent SITE only, not the
filesystem directly. Defer this deletion until after the production
cutover is complete.


REVISION NOTES
==============

v1 (May 26, 2026) — initial draft, dotrent.net build, assumed Plesk DNS
                    was the right path.

v2 (May 27, 2026) — corrected to customer-portal DNS after comparing
                    dotrent.net against the working gafol.rent setup;
                    added Step 6 (storage directory creation) after
                    hitting the 500 error from missing storage/framework
                    subdirectories on fresh Git deploy; reworked DNS
                    background section to explain the two-system reality.

v3 (Jun 06, 2026) — composer-after-deploy gotcha (Faker stripped on
                    gafol); sandbox authorized-recipient verification;
                    sandbox→Gmail delivery degradation + reconciliation
                    method; DEV_*_EMAIL added to Step 8.

v4 (Jul 04, 2026) — added Step 10b (create admin user on PRODUCTION by
                    hand: dev:reset refuses on production; is_admin and
                    email_verified_at are not mass-assignable — set via
                    forceFill + markEmailAsVerified or the admin is locked
                    out behind email verification). Written during the
                    fresh renters.rent production sibling build.


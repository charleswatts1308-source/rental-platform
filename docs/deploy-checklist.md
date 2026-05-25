# Production deploy checklist — Landlord Contact Service

Captures what stands between the merged `main` branch and a working production deploy on Plesk. Treat as a working document; tick boxes as you go and update with anything you learn during the first deploy.

## Status at point of merge

- LLCS feature complete: Phases 1–7 all shipped and merged to `main`.
- 373 tests green (`php artisan test`).
- Migrations are reversible and idempotent (verified up/down during dev).
- `composer.lock` committed — production deps reproducible from the lockfile.
- The end-to-end flow has only been exercised against Mailpit on localhost. **First production case-create will be the first time mail leaves real infrastructure.** Plan for issues.

## Pre-deploy: external setup

### Mailgun

The single biggest blocker. Without an active Mailgun configuration, outbound case notices don't leave the server and inbound landlord replies never reach the webhook.

- [ ] Mailgun account, **EU region** (`api.eu.mailgun.net`) for UK data residency.
- [ ] Sending domain `mg.renters.rent` configured. Mailgun's setup wizard generates the SPF/DKIM/DMARC records — add to DNS.
- [ ] Inbound subdomain `inbox.renters.rent` configured. Add the MX record Mailgun provides; this is the address the per-case `{token}@inbox.renters.rent` reply addresses route through.
- [ ] Inbound route in Mailgun pointing all `*@inbox.renters.rent` to `https://<your-domain>/webhooks/mailgun/inbound`.
- [ ] Webhook signing key generated. Copy this into `MAILGUN_WEBHOOK_SIGNING_KEY` in production `.env`.
- [ ] **`MAILGUN_CASES_FROM_ADDRESS` and `MAILGUN_INBOUND_DOMAIN` set in `.env`** — both are now **mandatory, no default**: `CaseNotice` throws and no case notices send if either is missing. Production values: `cases@mg.renters.rent` and `inbox.renters.rent`.
- [ ] **DNS propagation can take hours.** Don't deploy the same day you start Mailgun setup.

### Domain & HTTPS

- [ ] Production domain set up in Plesk.
- [ ] HTTPS via Let's Encrypt enabled — Mailgun **requires HTTPS** for webhook delivery.
- [ ] Document root pointed at `public/`, not the project root (Plesk: Hosting Settings → Document root).

## Production `.env`

Don't copy dev `.env` verbatim. Required values at minimum:

```
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generate via php artisan key:generate; do not reuse dev key>
APP_URL=https://<your-domain>

DB_CONNECTION=mysql
DB_HOST=<production db host>
DB_DATABASE=<production db>
DB_USERNAME=<...>
DB_PASSWORD=<...>

QUEUE_CONNECTION=database

MAIL_MAILER=mailgun
MAIL_FROM_ADDRESS=cases@mg.renters.rent
MAIL_FROM_NAME="renters.rent"

MAILGUN_DOMAIN=mg.renters.rent
MAILGUN_SECRET=<from mailgun api keys>
MAILGUN_ENDPOINT=api.eu.mailgun.net
MAILGUN_WEBHOOK_SIGNING_KEY=<from mailgun webhooks page>
MAILGUN_CASES_FROM_ADDRESS=cases@mg.renters.rent
MAILGUN_INBOUND_DOMAIN=inbox.renters.rent
```

- [ ] `.env` written, secrets stored only in `.env`, never committed.
- [ ] `php artisan config:cache` after `.env` is in place (and re-cache after any change).

## Server prerequisites (Plesk)

- [ ] PHP 8.x selected for the domain.
- [ ] PHP extensions enabled: `pdo_mysql`, `mbstring`, `intl`, `gd` (or `imagick`), `bcmath`, `xml`, `zip`.
- [ ] Composer available on the server (Plesk: usually `composer.phar` or installable via the Plesk extension catalogue).
- [ ] SSH access to the server (you will need it for the queue worker).

## Deploy procedure

Run from the project root on the production server:

- [ ] `git pull origin main`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=RepairCategorySeeder --force` (categories must exist or the case-create form has an empty dropdown)
- [ ] `php artisan storage:link` (if using public uploads later; not needed for current `local` private disk)
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] Verify `storage/` and `bootstrap/cache/` are writable by the web user.

## Long-running processes

These two **must** run continuously. Without them, the platform looks fine but mail piles up and sweeps never fire.

### Queue worker

`SendCaseNotice` queues mail from inside a DB transaction (`after_commit` semantics). The worker is required for any mail to actually go out.

Options on Plesk, in order of preference:

- **supervisord (proper way).** Requires SSH. Sample config:
  ```
  [program:llcs-queue]
  process_name=%(program_name)s
  command=php /var/www/vhosts/<your-site>/httpdocs/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  user=<web-user>
  redirect_stderr=true
  stdout_logfile=/var/log/llcs-queue.log
  ```
- **Plesk Scheduled Tasks fallback.** If supervisord is unavailable, a per-minute task running `php artisan queue:work --stop-when-empty --max-time=55` keeps mail moving with up-to-60-second latency. Acceptable temporarily; not great long-term.

- [ ] Queue worker running and supervised.

### Scheduler

The three daily sweeps (`cases:sweep-escalations`, `cases:sweep-holds`, `cases:sweep-dormancy`) are registered on Laravel's scheduler. The scheduler itself needs a per-minute cron entry to dispatch them.

- [ ] Plesk → Tools & Settings → Scheduled Tasks → add:
  ```
  * * * * * cd /var/www/vhosts/<your-site>/httpdocs && php artisan schedule:run >> /dev/null 2>&1
  ```

- [ ] Verify with `php artisan schedule:list` that all three sweeps appear.

## Post-deploy verification

- [ ] Hit `https://<your-domain>` and confirm the landing page renders.
- [ ] Log in (or register a test tenant) and confirm `/dashboard` works.
- [ ] Visit `/properties` — register a test property.
- [ ] Visit `/cases/create` — confirm the categories dropdown is populated (if empty, the seeder didn't run).
- [ ] Create a case against the test property using a **landlord email you control**. Confirm:
  - Redirects to `/cases/{slug}` with a success flash.
  - Mailgun's logs show the outbound notice within 1–2s (queue worker is alive).
  - The email arrives at the landlord address.
- [ ] Reply from the landlord address to the per-case Reply-To address. Confirm:
  - Mailgun's logs show the inbound webhook firing against your `/webhooks/mailgun/inbound`.
  - The reply appears on the case detail page in the dashboard.
- [ ] Check `case_events` for the test case — confirm the expected events were written (`case_opened`, `notice_sent`, `token_issued`, `inbound_received`).
- [ ] Trigger a sweep manually and confirm no errors:
  - `php artisan cases:sweep-escalations`
  - `php artisan cases:sweep-holds`
  - `php artisan cases:sweep-dormancy`

## Ongoing operations

- **Failed jobs.** Check `failed_jobs` table periodically. `php artisan queue:retry all` to retry, `php artisan queue:failed` to inspect.
- **Logs.** `storage/logs/laravel.log` and the supervisord/cron logs are the first stops when something looks off.
- **Mailgun events page.** Bounces, deferrals, and spam complaints land there. Item 6 in the design's "Open items to resolve before build" flags broader event handling as a future phase.
- **Disk usage.** `storage/app/private/cases/` accumulates uploaded photos. Monitor and have a retention strategy before it becomes a problem.

## Rollback

- [ ] Tag the pre-deploy commit on `main`: e.g. `git tag pre-deploy-<date> && git push origin pre-deploy-<date>`.
- [ ] If deploy goes sideways: `git checkout <pre-deploy-tag>`, `composer install --no-dev`, `php artisan config:cache`. Be aware that migrations are forward-only — a rollback in code does not roll back the schema. If a migration causes the issue, plan a forward-fix migration rather than `migrate:rollback` against a populated production DB.

## Recommended deploy path

Don't go straight to production:

1. Set up Mailgun + DNS first (slow due to propagation).
2. Provision a staging subdomain on Plesk (e.g. `staging.renters.rent`) with its own DB and Mailgun sandbox or test sending domain.
3. Deploy `main` to staging. Walk the post-deploy verification end-to-end with a real round-trip email.
4. Only after staging round-trips clean: tag, deploy to production, repeat the verification.

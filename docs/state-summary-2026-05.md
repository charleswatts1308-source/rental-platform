# LLCS — Current State Summary

## Where things stand

The Landlord Contact Service feature is **code-complete and merged to main** on GitHub. All seven planned phases (plus Phase 6.5) are built, tested, and committed. 373 tests, 846 assertions, all green.

**Current blocker:** production deployment paused due to discovery that the existing HostingUK shared hosting plan is Windows, which is a poor fit for Laravel. Decision to migrate to Linux hosting before launching.

## Code status

- **Repo:** `charleswatts1308-source/rental-platform` on GitHub
- **Branch state:** All work merged to `main`. Feature branch `feature/landlord-contact-service` retained for reference.
- **Rollback tag:** `pre-llcs-deploy` annotated tag on main, pointing at the merged commit.
- **Latest commit on main:** `09f2b23` (composer platform pin to PHP 8.2.30)

## What's built (the LLCS feature)

Repair notice generator and tenant correspondence engine:

- 8 new database tables: `landlord_contacts`, `properties`, `cases`, `case_messages`, `reply_tokens`, `case_events`, `message_attachments`, `repair_categories`
- Full state machine on `cases` with audit-grade event logging
- Outbound mail composition with stage-based escalation (4 stages, configurable later)
- Inbound webhook handling with HMAC verification, token resolution, HTML sanitisation, sender-mismatch quarantine
- Time-based jobs: escalation sweep, hold expiry, dormancy detection
- Tenant dashboard: case list, case creation, case detail, action panel (send-next, hold, resolve, abandon, re-engage)
- Properties registration UI (Phase 6.5)
- Tenant notification emails: landlord-replied, escalation-eligible, hold-expired, dormancy-reminder

## Mailgun setup

**Status: account active, domain verified, ready to use**

- Account: free tier, EU region, manual activation completed by support
- Domain: `mg.renters.rent` verified
- DNS records added at HostingUK (six records: SPF, DKIM, two MX, CNAME, DMARC)
- Credentials saved to 1Password:
  - API key (for `MAILGUN_SECRET`)
  - Webhook signing key (for `MAILGUN_WEBHOOK_SIGNING_KEY`)
- Inbound route not yet configured (waiting for production server URL)

## Hosting decision (the current pivot point)

**Existing Windows hosting at HostingUK is being abandoned for LLCS.**

History: Charlie originally used Windows Plesk shared hosting for an ASP.NET site, then a Razor Pages experiment. When LLCS started as a "lightweight Laravel project," it was deployed to the same Windows account for convenience. As the platform grew in complexity, the Linux/Windows mismatch became increasingly costly.

Trigger for the pivot: Windows Plesk caps at PHP 8.2.30, while Laravel's modern dependency tree increasingly assumes 8.4+. Forced a composer platform pin to keep things working.

Deeper issue: every Laravel community resource, tutorial, and Stack Overflow answer assumes Linux. Translation tax on every interaction. Claude Code itself defaulted to Linux assumptions and had to reorient.

**Decision:** migrate to Linux hosting before launching. No live users yet, so no migration of real data needed.

**Open question (waiting on HostingUK reply):** can existing Windows account be switched to Linux Plesk plan, or is a new Linux account needed? Either way, the migration mechanics are clear:

1. Linux server provisioned (existing or new HostingUK account)
2. Code pulled from GitHub
3. Database created fresh, migrations + seeder run
4. Mailgun setup unchanged (DNS already at HostingUK nameservers, valid for any server)
5. DNS A record for renters.rent repointed to new server's IP
6. SSL re-issued via Let's Encrypt

## Open items in the design doc

The main design doc has open items still valid post-migration:

1. Stage schedule legal review — 14/14/21 day defaults need eyeballing by housing law expertise before letters with legal weight go out
2. Hold duration UX (resolved Phase 6b — any future date)
3. Mailgun event/bounce handling — future phase
4. Inbound attachment processing — deferred from Phase 4
5. Configurable letter sequence (Phase 8) — design notes parked at `docs/phase-8-design-notes.md`

## Outstanding work to complete launch

In rough sequence:

1. ✅ Mailgun account active and domain verified
2. **Migrate to Linux hosting** ← blocking
3. Deploy code to Linux server (git pull, composer install, migrations, seed)
4. Configure scheduled task for `php artisan schedule:run` (one cron entry, every minute)
5. Update production `.env` with Mailgun credentials, set `MAIL_MAILER=mailgun`, set `QUEUE_CONNECTION=sync`, set `MAIL_FROM_ADDRESS=noreply@mg.renters.rent`
6. Configure Mailgun inbound route pointing at `https://renters.rent/webhooks/mailgun/inbound`
7. End-to-end smoke test: create case against a controlled landlord email, reply to the notice, watch the dashboard transition

## Decisions made during this conversation

- **Hold duration:** any future date, validated `after:today`. May tighten to constrained options if real usage shows tenants struggle.
- **Severity-driven schedule scaling:** deferred to Phase 8. v1 treats severity as informational only.
- **Configurable letter sequence:** Phase 8, post-launch. Snapshot pattern (`case_stages` table) for in-flight cases vs. new cases.
- **Letter content edit propagation:** admin chooses per-edit. Default "new cases only", explicit "apply to in-flight cases" as opt-in.
- **Bounce handling philosophy:** four-cases framing (reply / invalid reply / no reply / bounce). Mailgun events webhook future phase.
- **Queue worker:** `QUEUE_CONNECTION=sync` for v1. No worker process needed. Switch to `database` and proper worker if traffic grows.
- **Mail dispatch synchronous on registration:** addressed via `QUEUE_CONNECTION=sync` — all mail dispatches in-request, no queue delay.
- **Hosting:** moving to Linux. No real users to disrupt; cleaner architecture going forward.

## Operational decisions for production

- **Region:** Mailgun EU (`api.eu.mailgun.net`)
- **From address:** `noreply@mg.renters.rent` for general mail, `cases@mg.renters.rent` for LLCS notices
- **From name:** `"renters.rent"` for general, `"{tenant first name} via renters.rent"` for cases
- **PHP version:** 8.2.30 minimum (current Plesk Windows max). Will lift to 8.4+ if Linux Plesk offers higher.
- **Deploy pattern:** git pull on Plesk, composer install, artisan migrate, artisan db:seed, artisan config:cache

## Documents in the repo

- `docs/landlord-contact-service-design.md` — full design with deferred decisions log
- `docs/landlord-contact-service-implementation-plan.md` — phase-by-phase build plan
- `docs/phase-8-design-notes.md` — parked design for configurable letter sequence
- `docs/deploy-checklist.md` — production deployment checklist (currently Windows-Plesk-flavoured; needs revision for Linux)

## Pending decisions

- Whether to convert existing HostingUK Windows account to Linux, or open a new Linux account (waiting on HostingUK support reply, expected within ~12 hours)
- Whether to keep Windows account running its other sites or decommission entirely after migration

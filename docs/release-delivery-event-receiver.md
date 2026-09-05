# Release plan — delivery-event receiver (#25, release 1)

**Merge:** `4eed6a8` on `main`, tag `post-delivery-event-receiver`, suite 771.
**Closes:** the core of #25 — a bounced letter and an ignored letter are no
longer indistinguishable.
**Written:** 2026-09-05. Nothing deployed, nothing pushed.

---

## The one property that shapes this whole release

**Deploying changes nothing.** The receiver is a new endpoint that Mailgun
does not yet know about. Until the webhook is added in the Mailgun
dashboard, no event is ever sent and no code in this release executes.

So the deploy and the switch-on are **two separate acts**, and you can look
around in between. It also means rollback is a dashboard toggle, not a
redeploy.

## Why prod is where this gets proven

Standing position, ruled 24 Aug: **Mailgun webhooks are always tested on
live.** The sandbox cannot do inbound, so gafol can never receive one. That
limit is known and accepted, not worked around — do not build a synthetic
signature path to dodge it.

gafol still goes first, and still earns its place: it proves the two
migrations run on a box with history, that the templates seed without
clobbering edits, and that nothing else broke. It cannot prove the
receiver works.

---

## Step 0 — reconcile, on BOTH boxes

```
php artisan migrate:status
```

Compare against `environment-state.md`. Both boxes were reconciled 4 Sep,
so this should be clean and quick; do it anyway, and do it before pulling
so a surprise is attributable.

Expect **two Pending** afterwards:
- `2026_09_04_120000_add_contact_failed_to_cases_status_enum`
- `2026_09_05_120000_seed_contact_failed_tenant_notices`

## Step 1 — gafol

Plesk Git pull `main`, then:

```
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:clear
php artisan view:clear
php artisan migrate --force
```

**Verify on gafol:**

- [ ] Both migrations Ran. Neither prints anything; that is expected.
- [ ] `SHOW CREATE TABLE cases` — the status ENUM now carries
      **nine** values ending `'contact_failed'`, still
      `NOT NULL DEFAULT 'open'`. This is the #18 check on gafol's own
      engine; dev's pass does not transfer.
- [ ] The two template rows exist:
      ```sql
      SELECT code, active FROM letter_templates
      WHERE code LIKE 'contact_failed%';
      ```
- [ ] **Nothing else was reverted.** If any template wording had been
      edited on gafol, it is still edited. Spot-check one.
- [ ] The app works: open an existing case, raise a case, send a letter.
      This release touches the case page and the policy, so those are the
      surfaces to walk.
- [ ] `POST /webhooks/mailgun/events` with no signature returns **406**,
      not 404 or 500. Confirms the route and middleware are wired.

## Step 2 — prod

Identical to step 1. Same commands, same checks.

**Stop here and look around before step 3.** At this point prod is
carrying the code and behaving exactly as it did yesterday.

## Step 3 — subscribe the webhook (this is the switch-on)

Mailgun dashboard → the `mg.renters.rent` domain → Webhooks.

Add `https://renters.rent/webhooks/mailgun/events` for:

- **Permanent Failure**
- **Temporary Failure**
- **Delivered Messages**
- **Spam Complaints**

Names in the UI, not event names in the payload — remember there is no
`permanent_fail` event; the receiver branches on `severity`.

Do NOT point these at `/webhooks/mailgun/inbound`. That route is for
routing and returns **500** on an event payload (corrected 4 Sep — the D0
said 406).

The signing key is already configured; the inbound route has been
verifying against it for months.

## Step 4 — live fire

Raise a real case on prod to a **deliberately dead domain**, exactly as
the 23 Aug capture run did:

```
landlord@this-domain-does-not-exist-9f3k2.com
```

**Expect, within a minute or two:**

- [ ] the case moves to **contact failed**
- [ ] the case page shows the red panel: *"This notice could not be
      delivered"*, naming that address, with a working link to the
      landlord-contact correction
- [ ] an email arrives at your tenant address saying the notice could not
      be delivered
- [ ] `SELECT event_type, meta FROM case_events ORDER BY id DESC LIMIT 5;`
      shows a `delivery_failed` with `severity: permanent`,
      `reason: generic`, and a `case_message_id` matching the letter —
      plus a separate `case_contact_failed` for the stop
- [ ] the case offers **Abandon** and nothing else

**Then a control**, to prove the good path: raise a case to an address you
control and confirm a `delivery_confirmed` event appears and the case does
NOT stop.

**Then tidy:** abandon both test cases.

### What would tell you it is wrong

- Nothing happens at all → the webhook is not subscribed, or is pointed at
  the wrong URL. Check Mailgun's webhook log for the response code.
- Mailgun logs **406** → signature verification failed. Check
  `MAILGUN_WEBHOOK_SIGNING_KEY` in prod's `.env`, and scan for a duplicate
  `KEY=` line — Laravel takes the LAST and ignores earlier ones.
- Mailgun logs **500** → the events are hitting the inbound route.
- Event recorded but case not stopped → check the case's status was one
  D17.8 permits. A case already resolved or abandoned is left alone by
  design.

## Step 5 — the ledger, as the LAST step

`docs/environment-state.md` for both boxes: the commit and tag, the two
migrations, what was verified, **the date the webhook was subscribed**,
and the live-fire result. A deploy is not done until the ledger says it
happened.

---

## Rollback

**Before step 3:** nothing to roll back. The code is inert.

**After step 3:** remove the webhook subscription in the dashboard. Events
stop immediately and the code goes inert again. No redeploy, no migration
rollback.

The migrations are additive and safe to leave in place either way — the
ENUM gains a value nothing uses, and two template rows sit inactive.

**Do not roll back the ENUM migration while any case is in
`contact_failed`.** MariaDB would truncate the value. Abandon such cases
first, or leave the migration alone, which is the better answer.

---

## After this release

**Release 2 — the D17.3 tenant-taken copy.** A "copy this case" action on
a stopped case that carries the property, category, severity, description
and photos into the ordinary create-case flow, with the preview refusing
to confirm while the landlord email is still the one that bounced.

**When it ships, reword two surfaces** that currently say "correct the
address and raise a new case", because that will no longer be the best
route available:
- the `contact_failed_bounce` template (editable in the D16 editor)
- the bounce panel in `resources/views/cases/show.blade.php`

**Still unruled:** what a complaint should DO beyond stopping the case.
D17.5 makes it terminal and says it never forks; that is implemented. But
a complaint means a real person rejected our mail, and whether that should
suppress the address for future cases at that property is not decided.

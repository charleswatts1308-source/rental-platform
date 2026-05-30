# Session write-up — Sat 2026-05-30

Mailgun episode 3 follow-up session. Started with `dotrent.net` Laravel
build incomplete (no DNS resolution, no SSL, no demo data, no test send)
and ended with most of that resolved plus a meaningful shift in the
production cutover strategy.

---

## Decisions taken this session

### 1. Production cutover strategy — promote dotrent.net to renters.rent

The Linux Plesk install currently serving `dotrent.net` is the candidate for
production. It will be promoted to `renters.rent` via DNS flip rather than
treating it as a throwaway preprod and building production separately.

Reasoning:

- The current Windows `renters.rent` site is a failed build (architecturally
  flawed base discovered via the Mailgun issue at the start of episode 3).
  No real users on it.
- `dotrent.net` is a clean Linux Laravel install, identical codebase to
  `gafol.rent`. The work to make it production-grade is the same work whether
  it stays as `dotrent.net` or becomes `renters.rent`.
- Building a separate preprod environment now would be process for its own
  sake. A preprod environment can be built later if and when there is a
  specific need.

Saved to memory.

### 2. Mailgun production setup

`mg.renters.rent` is the single Mailgun production domain. One Mailgun
domain handles both outbound and inbound (per the KISS-on-inbound decision
from the previous session).

The inbound route will forward to `https://renters.rent/webhooks/mailgun/inbound`
after DNS flip. During pre-flip testing the same route can point at
`https://dotrent.net/webhooks/mailgun/inbound` since both URLs resolve to the
same Laravel install on the same Plesk server.

No `mg.dotrent.net` or other interim Mailgun domains — set up
`mg.renters.rent` once and use it for the production cutover.

Saved to memory.

### 3. Site inventory, final form

| Site | Role |
|------|------|
| `renters.rent` (Windows) | End of life. Failed build, no real users. |
| `renters.rent` (Linux, after flip) | Production. The current `dotrent.net` install. |
| `gafol.rent` | Staging. Mailgun sandbox. Stays as is. |
| `dotrent.net` | Becomes `renters.rent` via DNS flip. |
| `ukrenters.rent` | Earliest Linux site. Scheduled for deletion (carefully — nested vhosts). |

### 4. SSL hardening deferred

SSL Labs grades `dotrent.net` and `ukrenters.rent` at B because both servers
accept TLS 1.0 and 1.1. HSTS is off on both. Disabling old TLS and enabling
HSTS would lift the grade to A or A+ and also resolve Chrome's "Not Secure"
label on pages with password fields. Not blocking the LLCS work. Logged to
the snagging list mentally.

### 5. HUK customer-portal DNS is the chosen DNS path

`gafol.rent` uses HUK customer-portal DNS (ns1/2/3) and works fine. Plesk DNS
(ns10/11/12) was attempted on `dotrent.net` based on Plesk's banner advice,
which led to a dual-zone confusion that took an hour to debug. Reverted to
customer-portal DNS. The install recipe was rewritten accordingly. The
"two DNS systems at HUK" gotcha is now documented up front.

Cloudflare DNS is best industry practice (independent of host, better UI,
free) but moving all domains to Cloudflare is a separate task for later, not
blocking LLCS work.

---

## What was achieved on dotrent.net

1. **DNS resolved.** Customer-portal DNS path, A record `217.194.210.16`,
   nameservers `ns1/2/3.hostinguk.net`. Site reachable from the public
   internet.
2. **SSL working.** Let's Encrypt certificate issued via Plesk, covers apex
   and `www`. Grade B at SSL Labs (capped by TLS 1.0/1.1 support).
3. **Laravel runs.** Site loads at `https://dotrent.net`, homepage renders.
4. **Storage directories created.** Fix for the 500 "Please provide a
   valid cache path" error that appeared on first load after the Git deploy.
5. **DB created and migrated.** `ukrenter_dotrent_db` with 18 migrations
   applied cleanly.
6. **`.env` configured.** Copy from `gafol.rent` with 8 edited fields
   (`APP_NAME`, `APP_ENV=preprod`, `APP_KEY` blank-then-generated, `APP_URL`,
   DB credentials, `MAILGUN_CASES_FROM_ADDRESS` with `preprod@` local part).
7. **Recipe updated to v2** capturing the lessons:
   - DNS path corrected to customer-portal DNS
   - Storage directory creation added as Step 6
   - DNS-first ordering retained
   - Two-DNS-systems-at-HUK background section
   - Saved to `docs/huk-laravel-site-install-recipe.txt` artifact (needs
     committing to repo)

---

## Open issues at session close

### Blocked

**`dev:lifecycle` fails on `dotrent.net`** with HTTP 403 from Mailgun when
sending the stage-1 letter for case 1. Root cause: the demo data uses
`@example.test` landlord addresses which cannot be authorised on the
Mailgun sandbox.

CC commit `5f70dbb` already landed, adding `preprod` to the env allow-list
for `dev:*` commands. That unblocked the env-guard. The 403 is the next
blocker.

Path forward: set up `mg.renters.rent` as a real Mailgun custom domain
(not sandbox). With a real domain, Mailgun accepts sends to any address —
the `@example.test` sends will bounce undeliverable but won't 403. This
is the work for the next session.

### Pending CC work

**`dev:lifecycle` design improvement** — make it use one configurable
tenant email and one configurable landlord email (both real, both
authorisable) instead of generating 8 different fake addresses. Brief not
yet sent. Would solve the same `@example.test` problem more elegantly and
is the right structural answer regardless of which Mailgun domain is used.
Suggested env vars: `DEV_TENANT_EMAIL`, `DEV_LANDLORD_EMAIL`.

**NULL `sent_at` / `mailgun_message_id` / `from_address_raw`** on
`case_messages` rows — still hanging from Mailgun episode 3. Worth
resolving before production go-live so the DB audit trail is reliable.
Not blocking.

### Not yet started

**`mg.renters.rent` Mailgun setup** — the actual production Mailgun
domain. Needs:

1. Add `mg.renters.rent` as Mailgun sending domain (EU region)
2. Add DNS records (SPF, DKIM, MX × 2, CNAME, DMARC) to `renters.rent`'s
   DNS — wherever that's currently managed (likely HUK customer-portal DNS
   on the existing Windows site)
3. Configure Mailgun inbound route on `mg.renters.rent` pointing at
   `https://dotrent.net/webhooks/mailgun/inbound` for now,
   `https://renters.rent/webhooks/mailgun/inbound` after DNS flip
4. Wait for Mailgun domain verification (DNS propagation)
5. Update `dotrent.net`'s `.env` to point at `mg.renters.rent`

**DNS flip for `renters.rent`** — once `mg.renters.rent` is verified and
`dotrent.net` is exercising it cleanly:

1. Confirm what's on the current Windows `renters.rent` site (likely just
   a failed build with no real users)
2. Snapshot/backup if anything is worth preserving
3. Update `renters.rent`'s A record to point at the Linux Plesk server IP
   (`217.194.210.16`)
4. Add `renters.rent` to Plesk as a domain on the same subscription
5. Get SSL cert for `renters.rent`
6. Update `.env` on the Linux Plesk site with `APP_URL=https://renters.rent`
7. Update Mailgun inbound webhook URL to `https://renters.rent/...`
8. Smoke test: visit `https://renters.rent`, walk LLCS lifecycle

**LLCS lifecycle test plan walkthrough** — still pending. The test plan
artifact (`docs/llcs-lifecycle-test-plan.txt`) was drafted last session
but never saved to disk. Once `dev:lifecycle` works and production is
proven, this is the systematic walkthrough that replaces trial-and-error
testing.

---

## Artifacts needing commit to docs/

Both still in artifact pane only — never saved to repo:

1. `docs/llcs-lifecycle-test-plan.txt` (from previous session)
2. `docs/huk-laravel-site-install-recipe.txt` (v2 — rewritten this session)
3. `docs/session-writeup-Mon-2026-05-25-0630pm.md` (Mailgun episode 3 writeup)
4. `docs/session-writeup-Tue-2026-05-26-1030am.md` (last session writeup)
5. `docs/session-writeup-Sat-2026-05-30-1130am.md` (this writeup)

Worth a `git add docs/ && git commit -m "docs: session writeups, recipe v2,
lifecycle test plan" && git push` before starting the next session, so a
fresh chat can read these via the repo.

---

## VS Code layout status

Still working — chat group locked, snagging list anchoring the right pane,
diffs open in the right pane. No regressions this session.

---

## Next session pickup, in order

1. **Save the queued artifacts to disk and commit them.** Cleans up the
   chat-bound state.
2. **Send the CC brief** for `dev:lifecycle` 1-tenant-1-landlord design
   improvement. CC will be quick (~5-10 min historical).
3. **In parallel, start the `mg.renters.rent` Mailgun setup.** Add domain
   to Mailgun, get DNS records, add them to `renters.rent`'s DNS.
4. **When CC's commit lands, deploy to `dotrent.net` and run
   `dev:lifecycle`.** Should complete cleanly.
5. **Update `.env` on `dotrent.net`** to point at `mg.renters.rent` once
   it's verified.
6. **Run `dev:letter --case=1 --stage=1`.** Real send via
   `mg.renters.rent`, lands in real Gmail inbox (no Spam this time —
   DMARC aligned).
7. **Hit Reply in Gmail.** Reply round-trip via Mailgun inbound webhook.
   Verify case state transitions.
8. **DNS flip for `renters.rent`** once steps 6-7 prove the setup is
   solid.

---

*Filename: `session-writeup-Sat-2026-05-30-1058am.md`.*

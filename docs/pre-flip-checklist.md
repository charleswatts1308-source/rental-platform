# Pre-Flip Checklist — renters.rent production cutover

**File:** `docs/pre-flip-checklist.md`
**Purpose:** everything that must be true before the DNS flip of
dotrent.net → renters.rent. The flip is the last step of the cutover;
nothing here is optional. Living doc — add items as they surface, tick
when verified, never delete (strike through with a note instead).

Items are grouped by kind. Order within groups is not significance.

---

## A. Hard gates — flip is blocked until done

- [ ] **A1. Silence-model rewrite complete and proven on gafol.**
      Phases 1–5 merged, deployed to gafol, each phase gate passed.
      dotrent holds at pre-silence-model until the WHOLE model is
      proven, then takes it in one promotion. (Ruling Sat 2026-06-06.)
- [ ] **A2. Legal review of all outbound content.** The letters ARE
      the product; they invoke statute on tenants' behalf and (post-2b)
      fire automatically in their name. Requires a solicitor's opinion
      before clearance to fly. Scope: landlord wake-up letter(s),
      tenant nudges, exhaustion landlord closer, auto-escalation tenant
      notification, exhaustion-state signpost guidance (incl. the
      s.1 LTA 1985 absent-landlord claims — flagged as unverified
      training-data content), the s.11 LTA 1985 citation and day-count
      framing in the wake-up, Awaab's Law references, and the framing
      question: who is legally the sender — the tenant, or the platform
      as agent? Timing: after Phase 4 exists, so the full letter set is
      reviewed in one engagement. Post-sign-off, template edits
      re-trigger review (the template updated_at stamp on each sent
      message proves which wording version was in force).
- [ ] **A3. LLCS lifecycle test plan walkthrough on dotrent.** The
      systematic exploratory walkthrough, on the production candidate,
      with the production Mailgun domain. NOTE: the existing test plan
      doc describes the OLD ladder model — rewrite against the silence
      model before walking it.
- [ ] **A4. Full Mailgun round-trip re-proven on dotrent post-promotion.**
      Outbound to inbox (not Spam), inbound reply through webhook, token
      match, state transition, dashboard surfacing. (Was proven May 2026
      pre-rewrite; must re-prove on the silence-model codebase.)

## B. Configuration and infrastructure on dotrent

- [ ] **B1. Scheduler heartbeat task.** Plesk scheduled task, Run a
      command: `/opt/plesk/php/8.4/bin/php
      /var/www/vhosts/ukrenters.rent/dotrent.net/artisan schedule:run`,
      cron `* * * * *`. Without it NO sweep ever runs — in production
      that means escalations silently never fire. Verify the path via
      File Manager before saving; verify aliveness next day via
      silence:shadow-report rows. (Gap discovered on gafol Sat
      2026-06-06 — gafol had never had one.)
- [x] **B2. Inbound-domain configuration — satisfied by fail-loud
      guard, not by a default.** `config/services.php` reads
      `env('MAILGUN_INBOUND_DOMAIN')` with NO default, by design.
      `CaseNotice` throws `RuntimeException` + logs
      `'[LLCS] CaseNotice aborted'` if the value is blank, so a missing
      var aborts the send loudly rather than silently using the wrong
      domain. **REQUIREMENT:** every environment's `.env` MUST set
      `MAILGUN_INBOUND_DOMAIN` explicitly (and
      `MAILGUN_CASES_FROM_ADDRESS`, its sibling, same pattern). Do NOT
      add a default — that would reintroduce the silent-wrong-domain
      footgun this design prevents.
- [ ] **B3. dotrent .env final values.** APP_ENV=production,
      APP_URL=https://renters.rent (at flip), MAILGUN_DOMAIN=
      mg.renters.rent, MAILGUN_INBOUND_DOMAIN=mg.renters.rent,
      MAILGUN_ENDPOINT=api.eu.mailgun.net, production Mailgun keys,
      QUEUE_CONNECTION=sync. Remove/ignore DEV_*_EMAIL (dev:* commands
      refuse production anyway). config:clear after edits.
- [ ] **B4. composer install --no-dev on production** (post-promotion,
      per recipe: dev deps for staging/preprod, --no-dev for
      production). Note dev:lifecycle then cannot run there — correct
      and intended.
- [ ] **B5. Mailgun inbound route URL** updated from
      https://dotrent.net/webhooks/mailgun/inbound to
      https://renters.rent/webhooks/mailgun/inbound at flip time.
- [ ] **B6. SSL for renters.rent** on the Linux subscription (Let's
      Encrypt via Plesk, apex + www) once the domain is added.
- [ ] **B7. SSL hardening.** Disable TLS 1.0/1.1, enable HSTS —
      lifts SSL Labs grade from B and clears Chrome's "Not Secure" on
      password pages. (Deferred from May; production should not launch
      at grade B.)
- [ ] **B8. DNS flip mechanics.** renters.rent A records (apex + www)
      → 217.194.210.16 via HUK customer-portal DNS (ns1/2/3 — NOT
      Plesk DNS); add renters.rent as domain on the Linux subscription;
      confirm Windows-site content has nothing worth preserving;
      Windows renters.rent site retired.

## C. Known bugs that are free pre-flip, costly after

- [ ] **C1. Tenant dashboard link domain.** Notification emails carry
      dotrent.net URLs; must render from APP_URL so the flip fixes
      them — verify no hardcoded dotrent.net anywhere (grep the
      codebase and templates).
- [ ] **C2. Snag #4 — short human-quotable case references.** 6-char
      uppercase human-safe alphabet, random, unique. Zero migration
      cost now (preprod seed data only); painful post-go-live. Design
      decided, ready for CC as a standalone task.
- [ ] **C3. Snag list general sweep.** Walk
      docs/llcs-snagging-list.txt; fix or consciously defer each open
      item. The half-duplex snag closes via Phase 3; others (#1 nav
      title, #3 demo statements, #5/#6 login UX, #7 landlord lookup)
      need a fix-or-defer call each.

## D. Should-strongly-consider (not formally blocking)

- [ ] **D1. Snag #8 — delivery-status webhooks.** The evidential blind
      spot: 2026-06-06 testing showed sends can fail silently
      (Mailgun-accepted, Gmail-rejected) with the platform none the
      wiser. Production's aligned DMARC makes failures rarer, not
      impossible. Decide: build pre-flip, or accept launch with manual
      Mailgun-dashboard monitoring + a documented check routine.
- [ ] **D2. Backup/restore story for the production DB.** Verify HUK's
      backup cadence for ukrenter_dotrent_db covers an
      evidence-bearing service; know the restore procedure before
      needing it.
- [ ] **D3. Error visibility.** Production APP_DEBUG=off — decide how
      errors reach you (log review routine at minimum; mail-on-error
      or external monitor as options). A silent 500 on the inbound
      webhook loses landlord replies.
- [ ] **D4. ukrenters.rent deletion** stays DEFERRED until after the
      flip (nested-vhost warning in the recipe — gafol and dotrent
      live inside its vhost directory; delete the SITE via Plesk UI
      only, never the filesystem tree).

## E. At-flip smoke sequence (run in order, day of)

1. DNS flip per B8; verify with `nslookup renters.rent 1.1.1.1`.
2. https://renters.rent loads, SSL valid.
3. .env APP_URL updated, config:clear (B3).
4. Mailgun inbound route updated (B5).
5. Round-trip smoke: create a real case to a controlled landlord
   address; letter to inbox; reply; webhook; dashboard. (A4 condensed.)
6. Scheduler heartbeat verified firing (B1) — next-day check.
7. Watch Mailgun logs + Laravel logs for 48h (D3 routine).

---

*Origin: assembled Sat 2026-06-06 from items accumulated across the
Mailgun episodes, the silence-model rewrite sessions, and the gafol
deployment findings. Add to it; don't trust memory.*

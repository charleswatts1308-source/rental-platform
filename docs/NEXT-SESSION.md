# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has many files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-07-13. **renters.rent is LIVE and the hardening
tail is CLOSED** — cron, outbound mail and Mailgun inbound are all proven
on the real box. Prod is at `133a103` (**code-current**: carries the PWA
and the content rework; only two docs-only commits sit ahead on
`origin/main`). The escalation ladder is **under test in flight** on prod.
Suite **539 passing / 2239 assertions**.

---

## Parked state — read this first

The silence/email model (Phases 1–5, D1–D16) is complete, merged, and
**now running live on renters.rent**. The 4 Jul cutover is finished:

- **renters.rent (production)** — fresh sibling site on the Linux box,
  DNS cut over, SSL live, clean DB (`ukrenter_renters_db`). **Scheduler
  heartbeat confirmed running** (shadow-log rows at `06:15:02`),
  **outbound proven** (landlord letter delivered), **Mailgun inbound
  proven** (real landlord replies landing on cases). PWA + content rework
  live and tested.
- **gafol** — permanent staging. **dotrent** — preprod, awaiting retirement.
- The private-beta **registration gate** is built, merged and deployed.

**The 4 Jul "verification email sends nothing, logs nothing" LIVE ISSUE is
RESOLVED** — root cause was **duplicate `.env` keys** (within one file
Laravel takes the LAST `KEY=` and silently ignores earlier ones). Same
trap now baked into the install recipe, Step 8. Do not re-diagnose it.

**Authoritative record for current state: docs/environment-state.md** (the
deployment ledger). Supporting: `huk-laravel-site-install-recipe.md` (the
sibling build), `pre-flip-checklist.md` (wider/public-launch gates),
`dotrent-deploy-plan.md` (Phases A/B history only; Phase C SUPERSEDED).

---

## Next session — finish the escalation test, then open the family trial

**IN FLIGHT — the escalation ladder on prod (case 3).** Surface B set to
`escalation.interval_days=1` / `escalation.max_notices=2`, correctly
snapshotted onto the case (shadow log reads `0/1 days`, not the 14-day
default). Landlord letter 1 went out Sun 12 Jul evening; **first
escalation due at the 06:15 sweep on Tue 14 Jul.** Check
`silence_shadow_log` for `send_escalation` / `executed=1` plus the letter
in the landlord's inbox.

*Read the clock correctly before calling anything broken:* the sweep is a
**daily 06:15 batch**, and silence is floored to **whole days** — so a
1-day interval fires ~34h after the letter, not 24h. And because each sent
letter restarts the clock a beat *after* the sweep's own timestamp, later
rungs drift by one sweep (escalation 2 lands Thu, not Wed). Designed
behaviour, not a defect — harmless at the real 14-day interval.

**Then, to open the family trial:**
1. ~~Confirm the front door is locked.~~ **DONE (13 Jul):**
   `registration_open_to_all` = **false**, allowlist populated with 5
   family addresses (verified via `config:show app` on the box).
2. **Restore prod pacing:** put Surface B back to real intervals once the
   ladder test passes. B2 "Applies to in-flight cases" stays **Off**.
3. **Begin the family trial.** gafol stays permanent staging.

**⚠️ STILL OPEN (security):** rotate the Mailgun credentials —
`MAILGUN_SECRET` + `MAILGUN_WEBHOOK_SIGNING_KEY` were exposed in a
transcript on 4 Jul 2026 and are **not recorded as rotated**. Rotate in
the Mailgun dashboard → update the renters.rent (and dotrent) `.env` →
`config:clear`. Signing-key exposure weakens inbound-webhook authenticity
until done.

**Housekeeping:**
- **gafol branch:** it was temporarily pointed at `feature/pwa-wiring` for
  the iPhone install test. Confirm its Plesk repo is back on `main` and
  pulled, so staging tracks the main line.
- **Retire the old boxes:** confirm the Windows prod box is dark and record
  its retirement; retire dotrent once renters.rent is proven. End state:
  three live ledger entries — gafol, renters.rent, main.
- **Go-live switch (LATER, the only one):** open beyond family by setting
  renters.rent `REGISTRATION_OPEN_TO_ALL=true` + `config:cache`. Public
  launch still gated by solicitor wording sign-off — see
  `pre-flip-checklist.md`.

**Note:** solicitor letter-wording sign-off does NOT gate the family
trial (functional accuracy, family's own landlords — Charlie's call,
21 Jun 2026). It gates a wider/public launch.

---

## Read in this order (the live set)

1. **/CLAUDE.md** (repo root) — working agreements. Now carries the
   **Migrations** rule (manual MariaDB `SHOW CREATE TABLE` before merge)
   AND the **Deployment-ledger** rule (every long-lived deploy updates
   environment-state.md as its last step, reconciled vs `migrate:status`).
2. **docs/environment-state.md** — the deployment ledger. Current truth
   of what's deployed where (gafol + dotrent at `859827b`; main at
   `cef9875` — code tip `b165114` + the docs reconciliation).
3. **docs/dotrent-deploy-plan.md** — Phases A/B deploy history; Phase C
   (flip) SUPERSEDED. Current build runs off the sibling-site recipe.
3b. **docs/huk-laravel-site-install-recipe.md** — the fresh sibling-site
   build (incl. Step 10b admin creation on production).
4. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design (D1–D16).
   Wins over any brief; tie-breaker if two docs disagree.
5. **docs/User Guides/** — plain-English + technical dispatch-sequence
   references (which letter fires when), and the CC automation
   orientation (read-at-leisure).

Then execute the renters.rent cutover sequence above.

---

## Snags — open

`docs/llcs-snagging-list.txt`: **#7, #8, #12, #13, #17, #18, #19, #22.**
New this session: **#22** (no admin UI for repair_categories — phpMyAdmin
only; candidate for a future "Surface D" alongside the deferred
`letter_templates.active` toggle).

Resolved by Phase 5 (D16): #4, #14, #15, #16, #20, #21.

Deferred named gaps (not built): `letter_templates.active` toggle,
`ExhaustedStance` enum/`setStance` (dormant, no UI). Both candidates for
a post-#8 machine-state / Surface-D admin pass.

---

## Doc status map (design doc + ledger win when in doubt)

**LIVE — trust these:**
- `CLAUDE.md` — working agreements (Migrations + Deployment-ledger rules).
- `environment-state.md` — deployment ledger (current).
- `huk-laravel-site-install-recipe.md` — the fresh sibling-site build
  (current cutover build doc).
- `dotrent-deploy-plan.md` — Phases A/B deploy HISTORY only; **Phase C
  (flip) SUPERSEDED** by the sibling build.
- `llcs-silence-model-design.md` — authoritative design (D1–D16).
- `llcs-snagging-list.txt` — open snags (above).
- `pre-flip-checklist.md` — production cutover conditions (wider/public
  launch sign-offs).
- `User Guides/` — dispatch-sequence refs + automation orientation.

**HISTORICAL — accurate for their phase, don't lead with them:**
- `d16-cc-brief.md`, the D14/D15 briefs/reports/runbooks/write-ups, the
  phase-1/2a/2b/3 briefs + runbooks + write-ups.

**ARCHIVE — pre-silence-model; ignore for current work:**
- `LLCS Version 1/`, `LLCS old docs 3 May 1150/`,
  `landlord-contact-service-*.md`.

**VERIFY before relying on:**
- `phase-3-design-*.md` (likely folded into design doc),
  `phase-8-design-notes.md` (speculative), `deploy-checklist.md` (older;
  the live cutover doc is `dotrent-deploy-plan.md` + `pre-flip-checklist`),
  `huk-*`, `chats/*`, `state-summary-2026-05.md`, `session-writeup-*`.

---

## Branches

Merged, retained-but-deletable: `registration-lock` (this session),
`d14-escalation-exhausted`, `d15-engagement-gating`, `d16-admin-config-ui`,
`d16-admin-security`. Delete once cutover sign-offs land.

Tags: `pre-registration-lock` (`a63ac4a`), `post-d16-phase5` (`cf2f5c9`),
`pre-d16-phase5`, `pre-d16` — all on origin.

---

## Maintenance rule

When a phase closes: move its brief/report/runbook to HISTORICAL, repoint
the parked-state block, prune resolved snags. Keep this file to one
screen — it's a router, not a record. On any deploy, the LAST step is
writing environment-state.md (CLAUDE.md Deployment-ledger rule).

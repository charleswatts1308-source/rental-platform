# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has many files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-07-04 (plan changed: renters.rent built as its own
FRESH sibling site + DNS cut over to the Linux box tonight — NOT a DNS
flip onto dotrent; dotrent retired once renters.rent is proven). Deploy
Phases A+B (gafol+dotrent at `main`) + registration gate remain DONE.
**origin/main = `cef9875`** (docs-only ahead of code tip `b165114`).
Suite **539 passing / 2239 assertions**.

---

## Parked state — read this first

The silence/email model (Phases 1–5, D1–D16) is complete and merged, and
deployed + proven on gafol + dotrent with the registration gate — **all
of that is DONE**:

- **gafol (staging) and dotrent are both on current `main`** — Phase A +
  Phase B GREEN. dotrent was a clean `migrate:fresh` rebuild (all 35
  migrations, #18-clean; real categories/templates/settings, NO Faker
  user, empty cases).
- **The private-beta registration gate is built, merged, DEPLOYED, and
  VERIFIED on both boxes** (`15ec602`, tag `pre-registration-lock` =
  `a63ac4a`).

**The go-live plan has CHANGED.** Instead of flipping renters.rent DNS
onto dotrent, renters.rent is being built as its OWN fresh sibling site
on the Linux subscription (own DB, production config), and dotrent is
retired once renters.rent is proven. **Tonight (4 Jul 2026):** the
renters.rent sibling was built (DB `ukrenter_renters_db` clean, admin
created via recipe Step 10b) and its DNS A-records were cut over to the
Linux box (`217.194.210.16`) via the HUK customer portal (ns1/2/3).
Remaining: SSL, scheduler heartbeat, Mailgun inbound route, registration
lock, one round-trip verify, then retire the old boxes. See the next
section.

**Authoritative records for current state:**
- **docs/environment-state.md** — the deployment ledger; the renters.rent
  (IN PROGRESS) entry is current build state.
- **docs/huk-laravel-site-install-recipe.md** — the fresh sibling build.
- **docs/pre-flip-checklist.md** — cutover hard gates + smoke sequence.
- **docs/dotrent-deploy-plan.md** — Phases A/B history only; Phase C
  SUPERSEDED.

---

## Next session — renters.rent sibling build: finish the cutover

**Plan changed** (4 Jul 2026): renters.rent is built as its own FRESH
sibling site, NOT flipped onto dotrent. dotrent is retired once
renters.rent is proven.

**DONE tonight (4 Jul 2026) — completed history:**
- renters.rent built fresh on the Linux subscription (own site alongside
  dotrent.net + gafol.rent), DB `ukrenter_renters_db` clean (35
  migrations batch 1, cases=0, users=1 admin), reference tables seeded,
  admin created (recipe Step 10b, production tinker path).
- **DNS cut over:** renters.rent A records (apex + www) pointed at the
  Linux Plesk server `217.194.210.16` via the HUK customer-portal DNS
  (ns1/2/3 — NOT Plesk DNS). renters.rent now resolves to the sibling
  site; the old Windows box is superseded.

**⚠️ DO-NOW (security, independent of the cutover):** rotate the Mailgun
credentials — `MAILGUN_SECRET` + `MAILGUN_WEBHOOK_SIGNING_KEY` were
exposed in a transcript on 4 Jul 2026. Rotate in the Mailgun dashboard →
update the renters.rent (and dotrent) `.env` → `config:clear`. Signing-key
exposure weakens inbound-webhook authenticity until rotated. Recorded in
the ledger (dotrent entry).

**Remaining (needs LIVE access — human drives, Claude guides):**
1. **SSL:** Let's Encrypt (apex + www) once the domain resolves; then TLS
   1.0/1.1 disable + HSTS (checklist B6/B7).
2. **Scheduler heartbeat:** Plesk cron `schedule:run` every minute —
   without it NO sweep runs and escalations never fire (checklist B1).
3. **Mailgun inbound route** →
   `https://renters.rent/webhooks/mailgun/inbound` (checklist B5).
   Outbound works regardless, so a missed route only shows when a
   landlord reply fails to land. Don't skip it.
4. **Confirm ONE inbound round-trip** on the live renters.rent route.
5. **Registration lock:** set `REGISTRATION_OPEN_TO_ALL=false` +
   `REGISTRATION_ALLOWLIST=<family emails>` in renters.rent `.env` +
   `config:cache`. Front door locked to allowlisted family only.
6. **Begin the family trial:** Surface B short intervals for observable
   pacing; B2 "Applies to In-flight cases" stays **Off**. gafol stays
   permanent staging.
7. **Ledger — retire the old boxes:** confirm the Windows prod box dark
   and record its retirement; retire dotrent once renters.rent is proven.
   End state: three live entries — gafol, renters.rent, main.
8. **Go-live switch (LATER, the only one):** open beyond family by setting
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

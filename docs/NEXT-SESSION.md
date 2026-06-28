# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has many files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-06-28 (deploy Phases A+B green; registration gate
deployed + verified on both boxes). **origin/main = `b165114`**. Suite
**539 passing / 2239 assertions**. Only Phase C (DNS flip) remains.

---

## Parked state — read this first

The silence/email model (Phases 1–5, D1–D16) is complete and merged.
This session deployed it outward and locked the front door — **all of
that is now DONE**:

- **gafol (staging) and dotrent (production candidate) are both on
  current `main`** — Phase A + Phase B GREEN. dotrent was a clean
  `migrate:fresh` rebuild (all 35 migrations, #18-clean, production-
  candidate seed shape: real categories/templates/settings, NO Faker
  user, empty cases).
- **The private-beta registration gate is built, merged, DEPLOYED, and
  VERIFIED on both boxes** (`15ec602`, tag `pre-registration-lock` =
  `a63ac4a`). gafol `REGISTRATION_OPEN_TO_ALL=true` (open path verified).
  dotrent `REGISTRATION_OPEN_TO_ALL=false` + `REGISTRATION_ALLOWLIST=<2
  family emails>` — **lock verified working live.**

So the box is fully live and tested on dotrent with the front door
locked. **The ONLY work left is Phase C (the DNS flip) + the deliberate
later go-live switch.**

**Authoritative records for current state:**
- **docs/environment-state.md** — the deployment ledger (what's deployed
  where; per the CLAUDE.md Deployment-ledger rule).
- **docs/dotrent-deploy-plan.md** — the phased deploy (A done, B done,
  C = DNS flip remaining).

---

## Next session — Phase C (the DNS flip)

Deploy + registration lock are DONE (see parked state). All remaining
steps need LIVE access (DNS / Mailgun) — the human-in-the-loop drives,
Claude guides.

1. **DNS flip:** renters.rent → the dotrent install (Windows renters.rent
   is EOL).
2. **Update the Mailgun inbound route** →
   `https://renters.rent/webhooks/mailgun/inbound`. The one edit a flip
   silently leaves stale — outbound keeps working, so a missed inbound
   route only shows when a landlord reply fails to land. Don't skip it.
3. **Confirm ONE inbound round-trip** on the live renters.rent route —
   "the flip didn't break it" (the path is already proven on dotrent).
4. **Begin the family trial:** Surface B to set short intervals for
   observable pacing; B2 "Applies to In-flight cases" stays **Off**.
   gafol stays permanent staging. The front door stays locked (dotrent
   `OPEN_TO_ALL=false`); only the 2 allowlisted family emails register.
5. **Ledger — prod retirement:** once the flip completes and prod is
   confirmed dark, record the flip as prod's LAST event in
   environment-state.md, then strike the prod entry (ledger → three).
6. **Go-live switch (LATER, the only one):** open beyond family by
   setting dotrent `REGISTRATION_OPEN_TO_ALL=true` + `config:cache`.
   (Public launch still gated by solicitor wording sign-off — see
   `pre-flip-checklist.md`.)

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
   `b165114`).
3. **docs/dotrent-deploy-plan.md** — phased cutover (A+B done, C = flip).
4. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design (D1–D16).
   Wins over any brief; tie-breaker if two docs disagree.
5. **docs/User Guides/** — plain-English + technical dispatch-sequence
   references (which letter fires when), and the CC automation
   orientation (read-at-leisure).

Then execute the Phase C sequence above.

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
- `dotrent-deploy-plan.md` — cutover plan (current).
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

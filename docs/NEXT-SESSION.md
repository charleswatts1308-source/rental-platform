# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has many files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-07-27. **renters.rent is LIVE.** The onboarding +
content pass (`feature/onboarding-nav`) is now **MERGED to `main`
(`b92b907`) and deployed to gafol** (24 Jul, code-only, no migrations) —
it is no longer an open decision. A **non-prod hostname badge** shipped
with it: shows the domain (e.g. `gafol.rent`) on any non-production box so
you can tell at a glance you're not on prod — renders nothing on prod.

**Current in-progress branch: `feature/admin-unverified-users`** — a UI
usability pass (27 Jul), 8 commits, **pushed to origin, unmerged, not on
any box.** See its own section below. Its main output is a **design note
for the landlord-contact model** — a strong D0 candidate.

⚠ *Carried over from 18 Jul and NOT re-verified this session — check the
ledger / box before trusting: the prod commit, the in-flight
escalation-ladder test, and snag #23 (Mailgun rotation). They may already
be actioned; the sections below are as they stood on 18 Jul.*

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

## ✅ MERGED since — `feature/onboarding-nav` (was the open decision)

**Merged to `main` (`b92b907`, `--no-ff`) and deployed to gafol 24 Jul,
code-only.** The onboarding + content pass: dashboard turned into a real
hub, Dashboard in the main navbar, dead-ends fixed, new home page
(`welcome-4`, Erin version archived at
`/content-archive/homepage-erin-18-july-2026`), About restructured,
content archive sorts newest-first. All live on gafol now. Ledger updated.

## ⚠ UNMERGED WORK — `feature/admin-unverified-users` (27 Jul)

**8 commits, pushed to origin, NOT merged, NOT on any box.** A UI
usability session (views + docs only; suite unaffected, last green at
547/2265). Merge to `main` + deploy to gafol when happy.

- **Admin users page:** unverified users now listed under their own
  heading (previously counted but never shown); the verified table gained
  a "Verified On" timestamp.
- **Nav:** the "Properties & Cases" dropdown split into two top-level items
  **Properties** and **Cases**; nav labels no longer wrap mid-label on
  desktop (`white-space: nowrap`).
- **Dashboard:** green "Welcome — and thanks for registering!" banner on
  the post-verification landing (`?verified=1`).
- **Snags #27, #28, #29** added (see snags section).

### ⭐ Main output — landlord-contact model design note (D0 candidate)

`docs/landlord-contact-model-gap.md` (decision recorded 27 Jul). The
landlord contact hangs off the **case** via a global email-keyed table;
the **property has no landlord link**, so a mistyped landlord email can't
be corrected (snag #24 — **hit for real this session**). Agreed direction:
**property-owned, versioned contact with change-history; retire
`landlord_contacts`; ONE address per property** (the tenancy-agreement
service address is the legally-required item — the recipient circulates it
internally as they please), **no per-case override.** Integrity checked
**safe** — all evidential data is frozen at send/receipt (`to_address_raw`,
`bound_email`, `from_address_raw`), so relocating the live email rewrites
no past facts. **~5–6 focused days, D0-first, reconcile with the
silence-model design doc.** Sequencing: #25 (delivery-failure detection)
makes a typo *visible* and should come first.

---

## Next session — finish the escalation test, then open the family trial

**IN FLIGHT — the escalation ladder on prod (case 3).** Surface B set to
`escalation.interval_days=1` / `escalation.max_notices=2`, correctly
snapshotted onto the case (shadow log reads `0/1 days`, not the 14-day
default). Landlord letter 1 went out Sun 12 Jul evening; **first
escalation was due at the 06:15 sweep on Tue 14 Jul.** Check
`silence_shadow_log` for `send_escalation` / `executed=1` plus the letter
in the landlord's inbox. **Result not yet recorded here — confirm it and
write the outcome into the ledger.**

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

**✅ DONE 1 Aug — snag #23, the Mailgun credential rotation.**
`MAILGUN_SECRET` + `MAILGUN_WEBHOOK_SIGNING_KEY` are rotated; the keys
exposed in the 4 Jul transcript are dead after 28 days live. This was the
last outstanding item on prod. One check left: confirm **dotrent's** `.env`
was updated too — both boxes share `mg.renters.rent`, so rotating on one
leaves the other authenticating with a dead secret.

Also done 1 Aug: **three DNS records** (apex SPF, apex DMARC, tightened
`_dmarc.mg` — dropping the forensic `ruf`/`fo` reporting that would forward
tenant data to third parties), and **Mailgun open/click tracking confirmed
off in all three cases**. Full before/after in
`docs/DNS records old values.txt`.

**Housekeeping:**
- **Retire the old boxes:** confirm the Windows prod box is dark and record
  its retirement; retire dotrent once renters.rent is proven. End state:
  three live ledger entries — gafol, renters.rent, main.
- **Go-live switch (LATER, the only one):** open beyond family by setting
  renters.rent `REGISTRATION_OPEN_TO_ALL=true` + `config:cache`. Public
  launch still gated by solicitor wording sign-off — see
  `pre-flip-checklist.md`.
- ~~gafol branch back on `main`~~ — **DONE 13 Jul.** gafol and prod are
  level at `133a103`. Stage-then-prod discipline has been kept throughout.

**Note:** solicitor letter-wording sign-off does NOT gate the family
trial (functional accuracy, family's own landlords — Charlie's call,
21 Jun 2026). It gates a wider/public launch.

---

## Read in this order (the live set)

1. **/CLAUDE.md** (repo root) — working agreements. Now carries the
   **Migrations** rule (manual MariaDB `SHOW CREATE TABLE` before merge)
   AND the **Deployment-ledger** rule (every long-lived deploy updates
   environment-state.md as its last step, reconciled vs `migrate:status`).
2. **docs/environment-state.md** — the deployment ledger. Current truth of
   what's deployed where: **gafol at `b92b907`** (24 Jul, onboarding +
   badge); renters.rent per the ledger entry; dotrent awaiting retirement.
   `main` has advanced past docs-only (onboarding merge);
   `feature/admin-unverified-users` sits ahead of `main`, pushed, unmerged.
3. **docs/dotrent-deploy-plan.md** — Phases A/B deploy history; Phase C
   (flip) SUPERSEDED. Current build runs off the sibling-site recipe.
3b. **docs/huk-laravel-site-install-recipe.md** — the fresh sibling-site
   build (incl. Step 10b admin creation on production).
4. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design (D1–D16).
   Wins over any brief; tie-breaker if two docs disagree.
5. **docs/User Guides/** — plain-English + technical dispatch-sequence
   references (which letter fires when), and the CC automation
   orientation (read-at-leisure).

Then pick up the escalation test above.

---

## Snags — open

`docs/llcs-snagging-list.txt`: **#1, #2, #7, #12, #13, #17, #18, #19, #22,
#23, #24, #25, #26, #27, #28, #29, #30, #31, #32, #33, #34, #35, #36.**

*(#1 and #2 were open all along but missing from this list — corrected
1 Aug. #8 was CLOSED 1 Aug as a duplicate of #25, not as fixed.)*

The snagging list is where the **pre-live-running to-do list gets built
from**, so read the whole file before serious live running.

**Two priorities:**
- **#23** — rotate the exposed Mailgun credentials. Security, and still the
  oldest open item.
- **#25** — **no delivery-failure detection.** A bounced letter and an
  ignored letter are indistinguishable to the system, so the product will
  state "served on the 12th, no response in 14 days" with full confidence
  when nobody was ever served. Goes to the core claim. Roughly a day of
  plumbing, but the DESIGN questions come first — see
  `docs/delivery-failure-design-question.md`, written to be readable
  without repo access and **awaiting an outside review**.

Added 18 Jul: **#24** (can't correct a landlord email after send), **#25**
(above), **#26** (copy-anchored view assertions rot silently — a label
rename left an `assertDontSee` passing vacuously against a string no longer
in the app).

Added 1 Aug (mail identity + reply-path audit): **#31** (deploy-checklist
names `inbox.renters.rent`, a domain with no DNS — a rebuild following it
would silently break every landlord reply, and the `CaseNotice` guard tests
presence not validity), **#32** (`ContactReply` hardcodes an apex sender,
bypassing the fail-loud discipline), **#33** (apex SPF is a temporary shape,
blocked on #32), **#34** (CLAUDE.md's Mail section contradicts the code —
the keys have no defaults, by design), **#35** (local `MAIL_FROM_ADDRESS`
is a third-party domain), **#36** (Mailgun tier — open question, two
pre-sales questions before spending). Four of the six are minutes of doc or
config work; only #32 is code. **#25 gained the evidential argument** for
delivery webhooks and the wording discipline (name the MX host; delivered
≠ read). **#30 took a decision**: rebuild Contact Us as a real two-way
thread, not patch it.

Added 27 Jul (UI usability session): **#27** (verify link bounces
guest browsers to /login — cross-browser: register in Chrome, open the
verify email in Edge), **#28** (default the email field on non-prod auth
forms — dev convenience), **#29** ("Members Only" login banner is
intimidating; soften or suppress on the verify path). All three trivial +
deferred with fixes recorded. **#24 was PROMOTED 27 Jul** from convenience
to a data-design item — now fronted by `landlord-contact-model-gap.md`
(see the branch section above).

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
- `delivery-failure-design-question.md` — **standalone brief for outside
  review** (snags #24/#25). Written to be readable without repo access;
  poses the open rulings rather than answering them. **Awaiting a verdict —
  do not build #25 before it lands.**
- `landlord-contact-model-gap.md` — **design note + decision** (27 Jul),
  D0 candidate. Property-owned versioned landlord contact; retire the
  global email-keyed table; one address per property; fixes snag #24.
  Reconcile with the design doc before building.
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

**UNMERGED: `feature/admin-unverified-users`** — 8 commits, 27 Jul, pushed
to origin. The UI usability pass + landlord-contact design note described
at the top. Not merged, not deployed. **This is the current working
branch.**

`feature/onboarding-nav` — **MERGED** to `main` (`b92b907`) and on gafol;
deletable.

Merged, retained-but-deletable: `registration-lock`,
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

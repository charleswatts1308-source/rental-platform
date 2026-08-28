# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it current.
The `docs/` folder has many files and many are stale — this index says
which to trust and which to ignore, so you don't re-derive state from a
superseded doc. It is a **router, not a record**: keep it short.

**Last updated:** 2026-08-28.

**⏸ WORK IN FLIGHT — read this first.** `feature/property-landlord-contacts`
is **built, green, unmerged and unpushed**. 15 commits, tag
`pre-property-landlord-contacts` on main. It closes **#24**, **#49**
(both halves) and **#59**. Nothing is deployed. See "Resume here" below.

**✅ BOTH RELEASES ARE OUT.** The delivery-event capture run was deployed,
run and torn down the same evening — **verified gone**, not merely
disabled.

**renters.rent runs `02f1505`** (confirmed off Plesk, 24 Aug). This file
previously said `65540e1` — that was release 1's commit, and the
capture-run teardown redeployed `main` over it the same evening without
recording where it landed. `02f1505` is a docs commit, so prod's code is
exactly `a70065b`, and it carries **#47** (`7bcab73`).
**prod and gafol are code-identical** — `02f1505..bd80e12` is docs-only.

**gafol is CURRENT** at `bd80e12` (pulled 24 Aug). Staging-at-or-ahead
holds. The earlier claim here that gafol was behind at `7507a72` was
wrong — it had release 1 on 23 Aug and was tested on it; see the
correction block at the top of `environment-state.md`.

**One open branch:** `feature/property-landlord-contacts` (below).
`feature/delivery-events` and `feature/delivery-capture` are both
deleted; everything worth keeping is on main. Tag `pre-delivery-events`
still marks the fork point.

---

## Resume here — property-owned landlord contacts (#24 + #49)

Built 27–28 Aug on `feature/property-landlord-contacts`. **Suite green at
701** (main's real baseline was 578, not the 377 that was being quoted).

The landlord contact now belongs to the **property**, versioned. Routing
resolves the property's CURRENT contact; the case's
`property_landlord_contact_id` is provenance and is never read for
routing. `landlord_contacts` is dropped. `case_messages` was not touched.

**➡ Read `docs/cc-report-property-landlord-contacts-implementation.md`
first** — §7 is what is NOT covered, and it is the whole of what remains.

**Before merge, in this order:**

1. **`migrate:fresh` on MariaDB — NOT DONE.** The dev DB user has no
   `CREATE DATABASE` right, so no scratch database could be made, and
   running it against `rental_platform` would have destroyed dev data.
   The full chain (create → backfill → drop) is exercised on SQLite every
   suite run but **never on MariaDB**. Do this on gafol.
2. **Browser walk — DONE 28 Aug, complete.** Driven over real HTTP, then
   walked by Charlie in the browser including the multi-property
   JavaScript toggle. It found **three** things: the duplicate `class`
   attribute (`d7aba9a`), the self-referential correction events
   (`82b7fc2`), and **#59** — the preview never showed the landlord's
   email address. All three are now fixed; #59 was built the same day
   (`376946b`). Mailpit confirmed #24 end to end: first letter to
   `typo@`, second to `correct@`.
3. **Backfill on real data.** It has only ever run against dev's 5 cases
   (3 versions, 0 orphans, clean). gafol and prod shapes are unknown from
   here. The migration prints the orphan count at migrate time — that
   number belongs in the ledger.
4. `--no-ff` merge, then ledger.

**Behaviour changes to expect when walking it:** a reply from a
superseded address now quarantines; a second case at a property inherits
its landlord and cannot override it; saving a correction sends nothing.

**Deliberately not built:** a non-escalating "resend to the corrected
address". Auto-sending on correction takes `SendCaseNotice`'s non-first
branch and would escalate the case as the price of fixing a typo —
against D3. Needs a `stage_at_send` ruling against the silence design doc
before it can exist.

---

## The one thing that changed everything on 23–24 Aug

**#25 is unblocked and specified.** The capture run answered every
question it existed to answer, from observed bytes rather than
documentation.

**➡ `docs/mailgun-delivery-event-payloads.md` is the reference the
receiver gets built against.** Read it before writing a line of #25.

Headlines:
- The event signature is **nested**; our existing middleware reads the
  flat inbound shape, returns **406**, and Mailgun treats 406 as a
  deliberate refusal and **never retries**. A second middleware is
  confirmed necessary.
- Payloads are **JSON**, not form-encoded like the inbound route.
- **There is no `permanent_fail` event.** It is `failed` +
  `severity: permanent|temporary`. A parser keyed off the name the
  subscription UI uses would silently match nothing.
- A real bounce and a suppressed-address drop are BOTH
  `failed`/`permanent`. The discriminator is **`reason`** —
  `generic` vs `suppress-bounce`.
- **Our correlation key works.** Every outbound letter now carries
  `case_message_id` as a Mailgun custom variable (`a70065b`), and all
  three real sends came back with it. The receiver can bind an event to
  its `case_messages` row directly.

**Agreed ruling on what a detected bounce should DO:** a bounce is not a
variant of silence, it is the opposite. Silence escalates; a bounce must
**stop the ladder** and hand the problem back to the tenant. The
`contact_failed` status is the right shape.

**RULED 24 Aug — the bounce reaction, and #24's relationship to it.** On
a permanent failure: **record** the bounce as a `case_events` row (it is
part of the evidence record), **stop** the case with
`transitionTo(contact_failed)`, and **notify the tenant mail-only** —
the letter bounced, the case is stopped, correct the address and raise a
new case. Nothing further.

That recovery is abandon-and-re-raise, which **works today**, so #25
promises nothing the system cannot deliver and **#24 is NOT a
prerequisite**. #24 is scheduled on engineering grounds alone and
**releases separately** — it is a new table, a backfill, an FK swap and a
`DROP TABLE`, and should not share a merge with a webhook receiver.
Full rulings are in the snagging list under #24 and #25.

---

## Open actions — do these first

1. **Abandon the three test cases on prod:** `9RKDKC`, `3YHRKZ`,
   `CZPUAD`. Real cases raised for the capture run.
2. **Run the suppression SQL** (in `release-attachments-and-capture.txt`)
   against `charles.watts1308-t1@gmail.com` — a real July hard bounce,
   proven on 23 Aug to be STILL silently swallowing letters. Find out
   what it has done to its cases: whether the ladder ratcheted against
   letters never transmitted.
3. **Finish verifying the prod attachment release.** Deployed but NOT
   verified: `/landlords` renders with ICO ref `Z229825X`; nav order;
   admin ceiling reads 1; an attachment-bearing letter sends and
   arrives; the 4–8MB band gives OUR file-named error, not PHP's; and
   **spam-scoring an attachment-bearing letter, which has never been
   done on any path.**
4. **Reconcile the ledger against `migrate:status`** — not done since
   27 Jun, contrary to the CLAUDE.md rule. Overdue, and now blocking:
   the landlord-contact branch adds **five** migrations, and its drop
   step needs to know what has actually run where.
5. **#56** — advise the ICO of renters.rent as a trading name on
   registration `Z229825X`. Admin task, not code.

**Still outstanding from before 9 Aug, unconfirmed:** restore prod
pacing to `interval_days` **14** / `max_notices` **4** (dropped to 1/2
for the July ladder test); close out case 3; confirm the registration
allowlist; settle #27's 403 by reading the prod log.

---

## What shipped 23 Aug

**Release 1 — attachment policy + more.** Wider than its name: also the
new public `/landlords` page, the nav change, and the cases content line.
**Ships at ceiling 1 BY DECISION**, not by default — ceiling 1 is the
tested capability (#54), and #53 is unreachable at 1 and armed above it.
**Do not raise the ceiling until #53 is fixed.**

**Release 2 — capture run.** On, three real sends, off, same evening.
Findings above.

**#47 merged to main separately** (`7bcab73`) so the disposable branch
could be deleted without destroying it. Exhaustive status classification;
prerequisite for #25, which adds a `contact_failed` status. **It is
already LIVE on prod** — it rode in on the capture-run teardown redeploy.
Do not plan #25 as though #47 still needs shipping.

**Mailgun webhooks are ALWAYS tested on live** — standing position, ruled
24 Aug. The sandbox cannot do inbound, so gafol can never receive one;
that limit is known and accepted, not worked around. Release 2 was
deployed to gafol first, correctly, and simply could not be exercised
there. Don't build a synthetic test path to dodge this, and don't treat
"unstageable" as a blocker. The 23 Aug capture run is the pattern: deploy
behind a token, exercise with real sends, tear down and verify gone.

**#55 fixed and shipped** — `/landlords` now publishes the real ICO
reference `Z229825X`. The old value was the payment/account number.

---

## Snags — open

**#1, #2, #7, #12, #13, #17, #18, #19, #22, #25, #26, #27, #28,
#29, #30, #31, #32, #33, #34, #35, #36, #37, #38, #39, #40, #42, #44,
#48, #50, #51, #52, #53, #54, #56, #57, #58.**

**BUILT, NOT MERGED, NOT DEPLOYED: #24, #49, #59.** Still live on prod until
`feature/property-landlord-contacts` ships. #7 is the same defect as
#49(a) and dies with it.

Closed: **#23**, **#8**, **#41**, **#43**, **#45**, **#46**, **#47**,
**#55**. Resolved by Phase 5 (D16): #4, #14, #15, #16, #20, #21.

**Added 22–23 Aug, walking the releases:**
- **#49** the preview shows one landlord name and the letter sends
  another. **Needs a REPEAT landlord email** — the first case against an
  address is correct, later ones aren't — and it affects **all four
  letters**, not just the first. Live on prod.
- **#50** severity is a REQUIRED field that reaches nobody and changes
  nothing. Design question, not a whitelist edit.
- **#51** postcode is format-checked but never verified to exist, and
  nothing cross-checks the city. A notice went out naming the wrong town.
- **#52** the admin case view shows a shorter address than the letter.
- **#53** Remove on one of several staged photos deletes them all.
  **A defect in this release**, not a pre-existing one. Contained by
  ceiling 1.
- **#54** attachment coverage above ceiling 1 is incomplete. Scope call.
- **#57** no `resources/views/errors/` at all — **the 413 design is
  finished and parked inside this entry**, ready to build with the rest
  of an error pass.
- **#58** the photo check is per-file only; nothing sums the selection
  against `post_max_size`. Safe today by arithmetic, not by design.

**Added 28 Aug, walking the landlord-contact branch:**
- **#59** the create-case preview never showed the landlord's EMAIL
  ADDRESS — only the name, and only incidentally inside the letter's
  salutation. The preview is the last free moment to catch the typo that
  #24 exists because of. **Built same day** (376946b).

**A pattern worth naming.** #46, #49 and #53 are the same failure: a
surface asserting something the behaviour does not honour. Three in one
feature area. Standing rule now: **no surface may claim what the system
cannot deliver.**

**A second pattern, from git.** Three separate pieces of permanent work
(#47, the D0 report, snag #48) accumulated on a branch created for a
throwaway purpose, whose written teardown would have destroyed all three.
Check before deleting a branch; check what a "temporary" branch has
quietly collected.

---

## Read in this order

1. **/CLAUDE.md** — working agreements. Carries the Migrations rule
   (manual MariaDB check before merge) and the Deployment-ledger rule.
2. **docs/environment-state.md** — the ledger; current truth of what is
   deployed where.
3. **docs/mailgun-delivery-event-payloads.md** — the #25 receiver's
   specification, from observed bytes. NEW 23 Aug.
4. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design
   (D1–D17). Wins over any brief.
5. **docs/llcs-snagging-list.txt** — the running to-do list.
6. **docs/huk-laravel-site-install-recipe.md** — the sibling-site build.
   **STEP 1b is new**: where PHP limits actually live (CloudLinux PHP
   Selector → Options, subscription-wide), that Plesk's per-domain PHP
   Settings page is inert for every directive, and that artisan is never
   a valid way to read them — CLI and web load separate ini files.

---

## Doc status map (design doc + ledger win when in doubt)

**LIVE — trust these:** `CLAUDE.md`; `environment-state.md`;
`llcs-silence-model-design.md` (authoritative);
`mailgun-delivery-event-payloads.md`; `llcs-snagging-list.txt`;
`huk-laravel-site-install-recipe.md`; `release-attachments-and-capture.txt`
(both releases now DONE — kept as the record of how they were run);
`DNS records old values.txt`;
`cc-report-property-landlord-contacts-d0.md` +
`...-implementation.md` (NEW 27 Aug — the #24/#49 build);
`delivery-failure-design-question.md`; `cc-report-delivery-events-d0.md`;
`attachment-policy-design.md`; `pre-flip-checklist.md`; `User Guides/`.

**HISTORICAL — accurate for their phase, don't lead with them:**
`d16-cc-brief.md`, the D14/D15 briefs/reports/runbooks, the
phase-1/2a/2b/3 briefs + runbooks + write-ups, `dotrent-deploy-plan.md`,
`landlord-contact-model-gap.md` (**superseded for build direction by the
D0 report — the D0 lists seven places the note is wrong, starting with
routing. Keep it as the record of how Model A was reached; do not build
from it**).

**ARCHIVE — ignore for current work:** `LLCS Version 1/`,
`LLCS old docs 3 May 1150/`, `landlord-contact-service-*.md`.

**VERIFY before relying on:** `phase-3-design-*.md`,
`phase-8-design-notes.md`, **`deploy-checklist.md` (contains the
known-bad `inbox.renters.rent` value — snag #31)**, `huk-*`, `chats/*`,
`state-summary-2026-05.md`, `session-writeup-*`.

---

## Maintenance rule

When a phase closes: move its brief/report/runbook to HISTORICAL,
repoint the parked-state block, prune resolved snags. Keep this file to
one screen. On any deploy, the LAST step is writing
`environment-state.md`.

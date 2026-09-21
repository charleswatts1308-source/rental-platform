# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it current.
The `docs/` folder has many files and many are stale — this index says
which to trust and which to ignore, so you don't re-derive state from a
superseded doc. It is a **router, not a record**: keep it short.

**Last updated:** 2026-09-20.

> **Pruned 15 Sep 2026.** This file had grown to 542 lines of discharged
> history — the #24/#49/#59 build, the 23 Aug capture run, the #25
> releases, four completed open-actions. All of it is recorded properly
> elsewhere (`environment-state.md`, the `cc-report-*` files,
> `mailgun-delivery-event-payloads.md`) and none of it was steering the
> next action, so it is gone from here rather than duplicated. Its own
> maintenance rule asks for exactly that.

---

## Where everything is, right now

- **`main` = the 20 Sep docs tip; code tip is `29450ed`.** Local and origin level.
- **BOTH BOXES ARE AT `29450ed`** — deployed and tested 20 Sep, ledger
  written. Everything ahead of them on `main` is DOCS ONLY: the deploy
  revision doc and this router. **No code is undeployed.**
- **Suite: 889 green.**
- **No work in flight.** `feature/inbound-enquiries` is merged
  (`--no-ff`, tag `pre-inbound-enquiries` marks the commit before it).
  `main` is safe to build from.

**Attachment ceilings, both boxes: letter 1 = `0`, replies = `3`.** Set
15 Sep, deliberately (#73). Photos are refused on the cold first letter
and allowed, up to three, once the landlord has engaged. **The
consequence, so it is not rediscovered as a bug:** a tenant whose
landlord never replies never attaches a photograph at all, and the case
escalates on the description alone. The create-case form says so, and
only in this configuration.

---

## 20 Sep 2026 — credentials, Contact Us, and a deploy asymmetry

**#75 took THREE fixes, and the pattern is the point.** Each was verified
against the symptom I had been shown; each time the wall had moved one
step earlier in the user's path:

1. the reset FORM was unreachable while signed in — fixed;
2. the page that SENDS the link was unreachable — fixed;
3. the LINK to that page did not exist anywhere a signed-in user could
   see — fixed (it now sits on the profile password form).

Only the third makes the feature usable by the person it was for. A green
test proved each step in isolation; none walked the path end to end.
**Charlie found all three by asking what the user would do next** — the
question the suite never asks. Also **#76**: the reset form now says "New
Password", not "Password", on the least forgiving screen in the product.

**#30 Contact Us — the August decision was revised, deliberately.** Not
the threaded rebuild: a notification on submit whose **Reply-To is the
user**, so an ordinary mail client answers them, plus the admin reply
moving off the dead `noreply@renters.rent` onto an address the enquiry
forwarder recognises. Charlie's reasoning: keep it simple until volume
dictates otherwise, especially now the forwarder exists. Accepted cost:
his REPLY lives in his mailbox, not on the platform.

**A production risk was found, and it is open — see open action 1a.** The
two boxes do not deploy the same way.

---

## 19 Sep 2026 — the inbound enquiry channel, and what it cost to get it

**➡ `docs/cc-brief-inbound-enquiries.md` and
`docs/cc-report-inbound-enquiries-implementation.md` are the record.
`docs/inbound-mail-schematic.txt` shows where any given address ends up
— read that first if the question is "where does mail to X go".**

Landlords now have a written channel that is not a case reply (#62):
`landlord-enquiries@`, `privacy@` and `info@` on `mg.renters.rent` are
recognised by the webhook and forwarded to a private mailbox. Built,
tested live on production, merged, deployed.

**The constraint that shaped it:** the Mailgun free tier allows ONE
inbound route and it is spent on the case-reply catch-all. Upgrading buys
volume, not deliverability (#36), so the discrimination moved into the
application instead. Mail to those addresses was already reaching the
webhook and being dropped; only recognition was missing.

**Three things found by walking, which no test would have caught:**

- **#74** — the forward's SPF/DKIM line read `? / ?` on every real send.
  The code read payload fields Mailgun does not send, and **the test
  fixture invented the same fields**, so the suite agreed with the bug.
  The #25 lesson, recurring four days later: a fixture for a third-party
  payload proves nothing unless it came from a real capture.
- **#75** — a signed-in visitor clicking a password-reset link was
  redirected to the dashboard and never saw the form. Stock Breeze
  scaffolding. It traps a tenant permanently signed in on a phone, who
  then finds the profile page demanding the password they have
  forgotten. **Fixed in THREE halves on 20 Sep — see below.**
- **#48's root cause had gone stale** — the apex DOES have MX records
  now (added ~1 Aug), so mail bounces rather than vanishing. The original
  diagnosis was right for the world it was written in, and nobody
  re-checked. A diagnosis has a date.

**Sender checks on forwards are exception-only:** silent when SPF and
DKIM both pass, loud when they do not. A pass never changes what the
reader does.

---

## The September fix cycle — what it did

**➡ `docs/cc-report-fix-cycle-sep-2026-implementation.md` is the record.**
Read it before touching cases, attachments, replies or verification. It
carries the test deltas (one assertion **inverted**, twenty repointed,
none weakened), the three defects worth knowing about, and what "closed"
means for #54.

Twenty-one snags closed, plus **#19** (attachments on tenant replies,
open since the June live-fire). **Ten of the twenty-one were raised BY
walking the app**, which is the argument for walking it.

Three were defects no test would have found:

- **#71** — a double-click on Send posted two evidential letters, and was
  unguarded on the escalation path, where the counter never resets (D3).
- **#72** — choosing a replacement photo wiped the ones the tenant kept.
  A deliberate rule with a test pinning it, correct at ceiling 1 and
  wrong the moment the ceiling rose. The first defect #54's coverage gap
  actually hid.
- **#73** — replies shared letter 1's attachment ceiling, against the
  design doc. Surfaced through a sentence Charlie asked for, not through
  code.

---
## Open actions

**1. ~~DEPLOY `main` to both boxes.~~ DONE 20 Sep** — both at `29450ed`, tested, ledger written.

**1a. STANDING DEPLOY RULE, and a decision still open: THE TWO BOXES DO NOT DEPLOY THE SAME WAY.**

**The rule, effective now:** any release that changes `composer.json` or
`composer.lock` is NOT done on prod until you run
`install --no-dev --optimize-autoloader` from prod's **Laravel Toolkit
Composer tab** — it exists and works, no terminal needed. Record that you
did it in that deploy's ledger entry. gafol needs nothing: it does this
itself.

**HUK replied 21 Sep — the route is decided. ➡ `docs/deploy-pipeline-divergence.md` is the doc to read.** No supported way to link the repo to prod's Toolkit application, and they will not say what recreating it does to a non-empty docroot — so that is out. Instead: reproduce the pipeline in the Git panel's **additional deployment actions**, which touches only tracked files and is undone by unticking a box. Their proviso is VERIFIED — `.env`, `storage/` data and `vendor/` are all untracked. Job not yet done; full backup first, and find the real `composer` and `php` paths before filling the box. **Future sites: create the application via Toolkit's "Add application from Git" flow from the start** — the one step that prevents a repeat.

**Also still open:** whether to build the DRIFT CHECK (an artisan
command comparing `composer.lock` against what is installed in `vendor/`)
so the box says its dependencies are stale instead of waiting for a user
to find the page that breaks. Not built. Recommended, because every other
failure this week was silent too.

**The background:** gafol runs a full Laravel deployment (maintenance mode, composer install, npm install); prod copies files and nothing else. Same Git settings on both, composer working on both — the difference is Plesk application integration, present on gafol and absent on prod. **The next release that adds a Composer package will install on gafol and BREAK PROD** (new code, old `vendor/`), with nothing in the deploy output to warn you. Either enable the integration on prod, or make `composer install --no-dev --optimize-autoloader` a mandatory typed step there. **Until one is chosen, treat any release touching `composer.json` as blocked for prod.** **➡ `docs/revision-2026-09-20-deploy-asymmetry.md` is the record** — what was believed, what is actually true, why (the Laravel application is linked to the Git repo on gafol and not on prod), and the agreed handling: run composer from prod's Laravel Toolkit COMPOSER TAB after any deploy that changes `composer.lock`, and do NOT try to link the repo on prod. Also in `environment-state.md`, 20 Sep entry.

**2. #62's sentence into the THREE DATABASES.** The seeder has it
(commit `9f6855e`), so a fresh box is right — but dev, gafol and prod
read templates from the DATABASE and the seeder does not overwrite
existing rows. Each needs the paragraph added through the **ADMIN
TEMPLATE EDITOR**, never raw SQL: the editor writes
`letter_text_change_history`, and an unexplained wording change on an
evidential letter is what you would later have to explain. Exactly the
trap #61 hit on 15 Sep. **Until this is done, landlords are not told the
enquiry channel exists** — the build is live but invisible.

The wording, as settled on a dev preview and now in the seeder, sits in
letter 1's FOOTER below the rule, not in the body:

> If you have a question about renters.rent itself rather than this
> repair — who we are, why you received this, or how your details are
> held — please write to landlord-enquiries@mg.renters.rent instead, so
> this thread stays a record of the repair.

Placement was deliberate: above "Yours faithfully" is the TENANT's
notice, signed in their name; below the rule is renters.rent speaking.
A service instruction in the body muddles who is speaking in a document
that may reach a council or a court.

**3. OPEN QUESTION, Charlie's call:** the FINAL NOTICE has its own
shorter footer and did NOT get that sentence. Arguably it should — a
landlord receiving the final notice is the most likely of all to ask who
we are — but it is the most adversarial letter in the ladder.

**4. #48 — half closed 19 Sep, half deferred with a reason.**
- CLOSED: the published compliance contact is now
  `privacy@mg.renters.rent` on both boxes, proven live. A data subject
  exercising their rights reaches a working address.
- CLOSED: the PROD admin login moved to a `+admin` plus-addressed
  mailbox and **a real password reset was completed** — the first time
  that path has ever been exercised on that account.
- STILL OPEN: **gafol's admin account is unchanged.** Note the trap
  before trying it: gafol sends through the Mailgun **sandbox**, which
  delivers only to **authorised recipients**, and a plus-addressed
  variant is a different string. Add it in Mailgun first or the reset
  will not arrive, for a reason that has nothing to do with the fix.
- STILL OPEN: `DevReset.php:56` and `DevLifecycle.php:29` seed local
  admins with the dead `admin@renters.rent`. Harmless — Mailpit catches
  any address — but they perpetuate the string.

**5. Stage 2 of the enquiry channel, when it earns its place.** Today an
enquiry exists ONLY in Charlie's Outlook: no record, no admin list, no
trail. Sketched at the end of the brief. Do not build it until the
traffic says it is needed — that traffic is also the evidence for
whether #62's wording held.

**No decision is blocking a build.** #60 is parked UNDECIDED on purpose
(see below); #62 is ruled and built.
gets no email when their case opens and letter 1 goes out. Asked
directly on 19 Sep, Charlie chose to defer and keep it on the list as an
option he has not made his mind up about. Do not re-ask each session;
raise it only if something new bears on it. (If ever built: mail-only,
or it inflates the ladder.)

**Prod pacing, confirmed 4 Sep:** `interval_days` **14**,
`max_notices` **4**. In-flight cases keep their
`silence_settings_snapshot` from clock start regardless
(`escalation.apply_inflight` ships off).

---

## Snags — open

**OPEN, and confident — 24:** #1, #9, #10, #12, #13, #17, #18, #25
(release 2 only), #26, #28, #29, #30, #31, #32, #33, #34, #35, #37, #42,
#43, #48 (half closed), #60 (parked, undecided), #62 (built; wording not
yet in the three databases), #63. (#56 closed 19 Sep.)

**Fixed and DEPLOYED:** #74 (19 Sep); #75 all three halves, #76 and the
#30 cheap fix (20 Sep). Nothing fixed is undeployed.

**~~DISPUTED — 6~~ SETTLED 19 Sep 2026: #4, #14, #15, #16, #20, #21 are
CLOSED.** Settled against the code, not the documents: all six were built
in D16 / Phase 5 (merge `cf2f5c9`, 21 Jun), are on `main`, and are live on
both boxes. The router was right; the snagging list was three months
stale, because D16 is the one phase with no `cc-report-*` to prompt the
stamping. Entries now carry the commit and the file. **One outcome
differs from what BOTH documents claimed: #21 was ruled Option C** —
remove the colliding cosmetic stance dropdown and nothing else. D14 is
NOT reversed; an exhausted case keeps reply/resolve/abandon and stays
revivable. The snag file's own "#21 RULING" block (drop "Abandoned",
exhausted = dead) is a withdrawn draft and is marked as such.

**What the 24 actually are:**
- **Dev-facing only, nobody sees them:** #9, #10, #12, #13, #17, #18,
  #26, #31, #34, #35, #43. Eleven of the twenty-four.
- **Admin tasks, not code:** #48.
- **Needs a ruling first:** #60, #62.
- **Real user-facing work:** #1 (nav restructure), **#25 release 2** (the
  tenant-taken copy of a bounced case — the largest remaining piece),
  #28, #29, #30, #37, #42, #63.

**Closed by the September cycle:** #2, #19, #27, #40, #44, #50, #51, #53,
#54, #57, #58, #61, #64, #65, #66, #67, #68, #69, #70, #71, #72, #73.

**Closed by D16 / Phase 5** (stamped 19 Sep, shipped 21 Jun): #4, #14,
#15, #16, #20, #21.

---

## Read in this order

1. **/CLAUDE.md** — working agreements. The Migrations rule (manual
   MariaDB check before merge) and the Deployment-ledger rule.
2. **docs/environment-state.md** — the ledger; current truth of what is
   deployed where.
3. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design
   (D1–D17). Wins over any brief. Worth knowing it can be diverged from
   without anyone noticing: #73 was exactly that.
4. **docs/llcs-snagging-list.txt** — the running to-do list.
5. **docs/cc-report-fix-cycle-sep-2026-implementation.md** — the most
   recent phase, and the shape of the current code.
6. **docs/mailgun-delivery-event-payloads.md** — the #25 receiver's
   specification, from observed bytes.
7. **docs/huk-laravel-site-install-recipe.md** — the sibling-site build.
   **STEP 1b**: where PHP limits actually live (CloudLinux PHP Selector →
   Options, subscription-wide), that Plesk's per-domain PHP Settings page
   is inert for every directive, and that artisan is never a valid way to
   read them — CLI and web load separate ini files.

---

## Doc status map (design doc + ledger win when in doubt)

**LIVE — trust these:** `CLAUDE.md`; `environment-state.md`;
`deploy-pipeline-divergence.md` (**read before any deploy that touches
composer**); `revision-2026-09-20-deploy-asymmetry.md` (how it was found);
`inbound-mail-schematic.txt`; `cc-brief-inbound-enquiries.md`;
`cc-report-inbound-enquiries-implementation.md`;
`llcs-silence-model-design.md` (authoritative); `llcs-snagging-list.txt`;
`cc-report-fix-cycle-sep-2026-implementation.md`;
`mailgun-delivery-event-payloads.md`; `attachment-policy-design.md`;
`huk-laravel-site-install-recipe.md`; `llcs-decisions-2026-09-12.txt`;
`DNS records old values.txt`; `pre-flip-checklist.md`; `User Guides/`.

**HISTORICAL — accurate for their phase, don't lead with them:** the
`cc-report-property-landlord-contacts-*` pair (#24/#49/#59);
`cc-report-delivery-events-d0.md` and
`release-delivery-event-receiver.md` (#25 release 1);
`gafol-deploy-plan-property-landlord-contacts.md`;
`release-attachments-and-capture.txt`;
`delivery-failure-design-question.md`; `d16-cc-brief.md`; the D14/D15
briefs/reports/runbooks; the phase-1/2a/2b/3 briefs + runbooks +
write-ups; `dotrent-deploy-plan.md`; `landlord-contact-model-gap.md`
(**superseded for build direction — the D0 report lists seven places it
is wrong, starting with routing. Keep it as the record of how Model A was
reached; do not build from it**).

**ARCHIVE — ignore for current work:** `LLCS Version 1/`,
`LLCS old docs 3 May 1150/`, `landlord-contact-service-*.md`.

**VERIFY before relying on:** `phase-3-design-*.md`,
`phase-8-design-notes.md`, **`deploy-checklist.md` (contains the
known-bad `inbox.renters.rent` value — snag #31)**, `huk-*`, `chats/*`,
`state-summary-2026-05.md`, `session-writeup-*`.

---

## How Charlie works, if you are new to this

He directs, I implement. He tests in a browser and reports what he sees;
the productive mode is to diagnose and fix in the same turn so he can
retest immediately, rather than describing and waiting. Ten of the
twenty-one snags this cycle closed were found that way.

Two things that do not lapse in that mode: every fix gets a snagging-list
entry even when it is fixed the same day, and the phase report gates the
merge.

Answer him with symptom, cost, and whether it is worth fixing now — not
code paths, not framework internals, not file:line. He does not know the
codebase and is not a PHP developer. The mechanism belongs in the snag
entry, which is mine to read.

**NEVER correct a history doc.** Ruled 20 Sep 2026: "they are more useful
staying unchanged even if wrong. They record what was, or was thought, to
be happening. New understanding should go into a revision doc." So install
recipes, phase reports, deploy plans and write-ups are left exactly as
written, however stale — see `revision-2026-09-20-deploy-asymmetry.md` for
the shape. The living docs are the exception and ARE rewritten: this
router, `environment-state.md`, the snag list (append a dated note under
the existing entry, do not rewrite it) and the design doc.

**When he says something behaves oddly, believe him before explaining it
away.** On 20 Sep he said twice that the two boxes deployed differently
and was twice told it was cosmetic. He was right both times, and the
answer turned out to be a production risk. He is the only one who watches
these screens.

---

## Maintenance rule

When a phase closes: move its brief/report/runbook to HISTORICAL, repoint
the state block, prune resolved snags. **Keep this file to one screen** —
it was allowed to reach 542 lines before 15 Sep, which is how a router
becomes a record nobody trusts. On any deploy, the LAST step is writing
`environment-state.md`.

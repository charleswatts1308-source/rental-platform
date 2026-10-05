# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it current.
The `docs/` folder has many files and many are stale — this index says
which to trust and which to ignore, so you don't re-derive state from a
superseded doc. It is a **router, not a record**: keep it short.

**Last updated:** 2026-10-05.

> **Pruned 15 Sep 2026.** This file had grown to 542 lines of discharged
> history — the #24/#49/#59 build, the 23 Aug capture run, the #25
> releases, four completed open-actions. All of it is recorded properly
> elsewhere (`environment-state.md`, the `cc-report-*` files,
> `mailgun-delivery-event-payloads.md`) and none of it was steering the
> next action, so it is gone from here rather than duplicated. Its own
> maintenance rule asks for exactly that.

---

## Where everything is, right now

- **`main` = `f78c93c`.** Local and origin level, working tree clean.
- **BOTH BOXES ARE AT `337edf0`** — gafol and renters.rent, code-identical,
  **48 migrations each**, deployed and verified 5 Oct 2026.
  **NOTHING IS UNDEPLOYED.** First time the two have been level since
  20 Sep. ➡ `docs/environment-state.md` carries both entries.
- **Suite: 914 green.**
- **No work in flight.** `feature/property-details` is merged (`--no-ff`,
  tag `pre-property-details`); the branch is pushed and can be deleted
  whenever. `main` is safe to build from.

**Attachment ceilings, both boxes: letter 1 = `0`, replies = `3`.** Set
15 Sep, deliberately (#73). Photos are refused on the cold first letter
and allowed, up to three, once the landlord has engaged. **The
consequence, so it is not rediscovered as a bug:** a tenant whose
landlord never replies never attaches a photograph at all, and the case
escalates on the description alone. The create-case form says so, and
only in this configuration.

---

## NEXT ACTION — nothing is deploying. Pick from these.

**The deploy is DONE and closed out.** Both boxes level, ledger written.
Do not re-derive deploy state from anything below; `environment-state.md`
is the record.

**1. #62 — THE ENQUIRY SENTENCE IS STILL NOT IN ANY DATABASE.** My pick,
and it has been outstanding since 19 Sep. The inbound enquiry channel is
BUILT AND LIVE on production, but letter 1 does not mention it, so **no
landlord is being told the channel exists**. The seeder has the wording;
dev, gafol and prod read templates from the DATABASE and the seeder does
not overwrite existing rows. Each box needs the paragraph added through
the **ADMIN TEMPLATE EDITOR**, never raw SQL — the editor writes
`letter_text_change_history`, and an unexplained wording change on an
evidential letter is what you would later have to explain in front of
someone. The exact wording and the reasoning for its placement in the
FOOTER rather than the body are in open action 2 below.

**2. The prod composer gap (open action 1a) — now the largest latent
risk.** Nothing has changed: prod installs no dependencies on deploy, so
**the next release that adds a Composer package will work on gafol and
break production silently**. This release was safe only because it added
none. The route is decided and written up in
`docs/deploy-pipeline-divergence.md`; the job is not done.

**3. Snag #77's three leftovers**, all small, all user-facing: an
unverified user still sees the Cases link and gets bounced with no
explanation; there is no active-page styling anywhere; and the 200px
logo is over half the width of a phone screen.

**4. The content decisions parked with Charlie** — the homepage
retaliation line, the jurisdiction sentence on How It Works, and
`/about` and `/landlords`, which have still never been looked at.

**WATCH THIS, it is new on 5 Oct:** property type and the lease question
are MANDATORY, and every existing property on both boxes holds the
backfill value. So **a user editing an existing property is now asked two
questions they have never seen**, and cannot save without answering. That
is the design working. It is also the most likely thing a confused user
reports, so recognise it rather than treating it as a bug.

---

## 5 Oct 2026 — property details, deployed

**➡ `docs/cc-brief-property-details.md` (D0) and
`docs/cc-report-property-details-implementation.md` are the record.**

Property type (mandatory, statistics only), "do you have a lease
agreement, even if you can't find it?" (mandatory, three answers), and an
optional lease upload on both property forms. Merged and live on both
boxes.

**Three things not to rediscover:**

- **`not_specified` is a BACKFILL MARKER on both columns, not an answer.**
  Not in the dropdowns, not in the markup, refused by validation — an
  existing property shows "— please choose —" instead. Both columns are
  NOT NULL with **no database default**, so a code path that forgets to
  ask fails loudly rather than inventing an answer.
- **The type list and ITS ORDER come from the Background page's sourced
  distribution**, so the two sets of figures can be read against each
  other. That is why flats are split purpose-built/converted and why
  there is NO end-of-terrace. "Room in a shared house" is deliberately
  not labelled HMO — that is a legal question about someone else's
  property and a tenant asked it will guess.
- **A LEASE NEVER LEAVES THE PLATFORM** — never attached to a case, sent
  to a landlord, or carried on a letter. Held so a human can read it to
  identify the landlord's formal service address. Stated in the
  migration, the model and the controller, and **two tests make it
  executable**. `file_attachments` was deliberately not reused: a table
  called "attachments" does not carry the rule.

**Nothing reads `property_type` yet.** It is collected and stored; no
admin view counts it. That is the obvious next piece if the statistics
are wanted rather than merely possible.

---

## 4 Oct 2026 — the nav, and the dashboard's removal (DEPLOYED)

**➡ Snag #77 is the record, and it closes #1.** Read it before touching the
nav, the footer or `/cases`.

**The signed-in nav is now `How It Works · Background · Cases`**, plus the
Admin dropdown. It was seven items, the first three of which were pages the
user had already finished with.

**THE DASHBOARD IS GONE.** `/cases` took over its duties — it is the
post-verification landing page, carries the onboarding signposting and
"Needs your attention", and is the only signed-in nav item. `/dashboard`
permanent-redirects to it. Charlie's reasoning: two pages showed the same
cases in different frames, and the cases list is the one people came for.
Six `route('dashboard')` redirects in the Auth controllers were repointed.

**`/cases` is master-detail** — properties newest first, cases newest first
within each, with "Raise a case here", "Landlord" and "Edit" on every
property heading. Properties left the nav because that page WAS those two
buttons. **The grouping is not cosmetic:** a tenant who moves keeps the
cases from the old address, because they are evidence, and cases are
fetched by tenant and then grouped so a missing property can never hide one.

**Contact Us is public, the enquiry address is not.** The link shows to
everyone and still needs a login; the login page now explains why in
contact-specific wording, including a line for a landlord. Charlie ruled
the `landlord-enquiries@` address OFF that page — a public page gets
harvested, and the letter already carries it.

**Breadcrumbs stay out, confirmed.** The layout's breadcrumb block has never
rendered and never will unless someone supplies `$breadcrumbs`. Kept and
commented as dormant-by-decision, not deleted, in case the site deepens.

**Three things left open and listed in #77:** the nav item shows on `@auth`
while the route needs auth+verified, so an unverified user still meets a
silent wall; there is no active-page styling anywhere; and the account
button is labelled with the user's email address.

---

## 30 Sep 2026 — content

**The site is structurally finished and was never the problem.** Public
pages are: `/` homepage, `/about`, `/members/how-it-works`, `/landlords`,
privacy, cookies — plus `/contact` behind login and an auth-only
escalation-routes stub kept out of the nav. That is a sufficient set for
launch. What has been unsettled through many attempts is the WORDING, not
the sitemap.

**The homepage is now a DOOR, not an explanation.** Charlie's decision:
the process is too involved to compress into landing copy without
overselling it. So the page says what the service is, gives one reason to
trust it, and offers two ways on — sign up, or read how it works. Two
lines were cut rather than reworded: "we will ensure a useful outcome"
(unpromisable, and contradicted eight lines below on the same page) and a
retaliation claim that gave a legal assurance to the reader most
frightened of exactly that.

**How It Works now carries the honesty the homepage dropped** — a fourth
outcome, THE LANDLORD REFUSES, which is the case a tenant most needs
warning about because the process has worked perfectly and they still have
no repair. Also removed: a list of renter categories ("Student · Young
professional · Family · Long-term renter"), because a list invites the
reader to look for themselves in it and conclude they are not there.

**New page: `/prs`** — the scale of the sector, England only, tables and
sources, no argument. **In the nav as "Background" since 4 Oct**, with the
page heading leading on the same word. Every figure re-fetched from the
primary source: the
archived version had cited a landlord survey for defence and retail
figures, and had given England's 4.7 million as a UK number.

**HOW CHARLIE WANTS DATA PRESENTED — this cost several iterations, so do
not relearn it.** Direct labelling, never a legend: "tables and charts with
legends require me to engage brain more than a fleeting glimpse will
allow". Proportional shading INSIDE the table cells beat a separate chart —
a stacked bar chart and a summarising sentence were both built and removed
the same hour. Shading is at a true 0–100 scale, never scaled to the
largest value, because the page's whole virtue is that it can be checked.

**Standing rule from the same session: no invented figures.** If a number
cannot be traced to a named source with a date, it does not go on the page.
The one derived figure shows its arithmetic and says it is ours.

---

## Discharged sessions — pointers only

Pruned 5 Oct 2026. All three below are fully recorded elsewhere and none
of them steers the next action; they were 150 lines of this router.

- **20 Sep — credentials, Contact Us, the deploy asymmetry.** #75 took
  THREE fixes, each verified against the symptom shown while the wall
  moved one step earlier in the user's path. ➡
  `docs/revision-2026-09-20-deploy-asymmetry.md` and
  `docs/deploy-pipeline-divergence.md` — **read the second before any
  deploy that touches composer.** The asymmetry itself is still open; see
  action 1a.
- **19 Sep — the inbound enquiry channel.** ➡
  `docs/cc-brief-inbound-enquiries.md`,
  `docs/cc-report-inbound-enquiries-implementation.md`, and
  `docs/inbound-mail-schematic.txt` — read that last one first if the
  question is "where does mail to X go". **Its wording is still not in any
  database: see action 2, which is the top of the next-action list.**
- **The September fix cycle.** ➡
  `docs/cc-report-fix-cycle-sep-2026-implementation.md`. Read it before
  touching cases, attachments, replies or verification. Twenty-one snags
  closed, ten of them found BY WALKING THE APP, and three defects no test
  would ever have found (#71 double-send, #72 replacement wiping kept
  photos, #73 replies sharing letter 1's ceiling).

**The lesson all three share, and the reason this project walks the app:
a green suite proves each step in isolation and never asks what the user
would do next.** #74 is the sharpest case — a test fixture invented the
same fields the buggy code read, so the suite agreed with the bug.

---

## Open actions

**1. ~~DEPLOY to both boxes.~~ DONE 5 Oct 2026** — both at `337edf0`,
verified, ledger written. Nothing is undeployed.

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

**1b. CONTENT DECISIONS LEFT WITH CHARLIE (30 Sep).** None blocking:
- ~~**Where `/prs` belongs**~~ — SETTLED 4 Oct: main nav, named
  "Background".
- **The homepage's retaliation line** still reads "You have new rights now
  that encourage you to ask". Agreed to be safe but to UNDERSELL: rights
  protect, they do not encourage, and the concrete fact — that the no-fault
  eviction route is gone — is both stronger and checkable. Charlie's field,
  Charlie's sentence.
- **A jurisdiction sentence on How It Works**, saying who the service is
  for. NOT written, deliberately: whether the Renters' Rights Act applies
  to England only or England and Wales is extent-versus-application and
  could not be settled from primary sources on 30 Sep. Charlie knows the
  answer; it is a sentence that tells someone whether the law protects
  them, so it must not be guessed.
- **`/about` and `/landlords` have not been looked at.**

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

**OPEN — 23:** #9, #10, #12, #13, #17, #18, #25 (release 2 only), #26,
#28, #29, #30, #31, #32, #33, #34, #35, #37, #42, #43, #48 (half
closed), #60 (parked, undecided), **#62 (built and live; WORDING STILL
NOT IN ANY DATABASE — the top of the next-action list)**, #63.

**#1 CLOSED 4 Oct 2026 by #77**, the nav restructure — with the OPPOSITE
outcome to the one #1 anticipated. It expected Cases and Properties to
move inside the Dashboard; what happened is the dashboard was removed
and `/cases` took over. Its entry carries a dated note.

**Fixed and DEPLOYED:** #74 (19 Sep); #75 all three halves, #76 and the
#30 cheap fix (20 Sep); **#77 (nav restructure), #78 (footer copyright
year) and #79 (two-row header + account email) — all live on both boxes
5 Oct.** Nothing fixed is undeployed.

**#77 left three small things open**, none of them blocking: an
unverified user sees the Cases link and is bounced with no explanation;
there is no active-page styling anywhere; and the 200px logo is over
half the width of a phone screen.

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

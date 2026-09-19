# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it current.
The `docs/` folder has many files and many are stale — this index says
which to trust and which to ignore, so you don't re-derive state from a
superseded doc. It is a **router, not a record**: keep it short.

**Last updated:** 2026-09-15.

> **Pruned 15 Sep 2026.** This file had grown to 542 lines of discharged
> history — the #24/#49/#59 build, the 23 Aug capture run, the #25
> releases, four completed open-actions. All of it is recorded properly
> elsewhere (`environment-state.md`, the `cc-report-*` files,
> `mailgun-delivery-event-payloads.md`) and none of it was steering the
> next action, so it is gone from here rather than duplicated. Its own
> maintenance rule asks for exactly that.

---

## Where everything is, right now

- **`main` = `72b0d8e`.** Local and origin level; nothing exists only on
  the dev box.
- **gafol.rent AND renters.rent are both on `12646e7`**, the September
  fix-cycle merge (tag `post-fix-cycle-sep-2026`). Both walked. Ledger
  written for both.
- **Suite: 861 green.**
- **No work in flight.** No branch is unmerged. `main` is safe to check
  out and build from.

**Attachment ceilings, both boxes: letter 1 = `0`, replies = `3`.** Set
15 Sep, deliberately (#73). Photos are refused on the cold first letter
and allowed, up to three, once the landlord has engaged. **The
consequence, so it is not rediscovered as a bug:** a tenant whose
landlord never replies never attaches a photograph at all, and the case
escalates on the description alone. The create-case form says so, and
only in this configuration.

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

1. ~~**gafol reconciled against `migrate:status`.**~~ **DONE 15 Sep** —
   43 Ran, none pending, identical set to prod. Both boxes are now
   reconciled and the item open since 27 Jun is closed. (Batch numbers
   differ between the boxes; that is deployment history, not drift.)
2. ~~**The dev box still carries the retaliation sentence.**~~ **DONE
   15 Sep** — #61 is now clear on ALL THREE environments (prod, gafol,
   dev), verified by querying the templates rather than by trusting the
   edit. A fresh install gets the corrected text from the seeder.
3. **#48 — `admin@renters.rent` cannot receive mail**, so its password
   reset is broken. Open since July, approach agreed 12 Sep, unbuilt.
   The one open item with real consequences.
4. ~~**#56** — advise the ICO of renters.rent as a trading name on
   registration `Z229825X`.~~ **DONE 19 Sep**, reported by Charlie. Entry
   closed in the snag list; nothing in the repo changed.
5. ~~**Older, unconfirmed since before 9 Aug:** close out case 3; confirm
   the registration allowlist.~~ **DONE 19 Sep**, both confirmed by
   Charlie. They had been carried unconfirmed for six weeks; they are
   not carried forward.

**No decision is blocking a build. #62 was ruled 19 Sep:** wording only,
in letter 1 — tell the landlord how to make a separate enquiry and ask
that such enquiries stay out of the repair thread. **No link**, so the
letter has the best chance of avoiding spam treatment. Explicitly a
first attempt: if it does not hold, think again. Landlords therefore get
**no** written channel that is not a case reply, which makes `admin@` the
front door rather than a stopgap.

**Next build, and it is a prerequisite: #48.** The #62 sentence needs a
destination and there is none — `admin@renters.rent` has received nothing
since 4 Jul. Build the Mailgun forward route, prove it receives, THEN
write the sentence against the address that results.

**#60 is NOT blocked — it is parked, undecided, on purpose.** A tenant
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
#43, #48, #60, #62, #63. (#56 closed 19 Sep.)

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

---

## Maintenance rule

When a phase closes: move its brief/report/runbook to HISTORICAL, repoint
the state block, prune resolved snags. **Keep this file to one screen** —
it was allowed to reach 542 lines before 15 Sep, which is how a router
becomes a record nobody trusts. On any deploy, the LAST step is writing
`environment-state.md`.

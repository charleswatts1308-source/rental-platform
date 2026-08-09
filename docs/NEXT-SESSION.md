# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it current.
The `docs/` folder has many files and many are stale — this index says
which to trust and which to ignore, so you don't re-derive state from a
superseded doc. It is a **router, not a record**: keep it short.

**Last updated:** 2026-08-09.

**⚠ NOT level any more. Two releases are waiting to go out.**
`main` is at **`1a46766`** (attachment work merged, 570 tests green).
The boxes are still at **`7507a72`** — gafol and renters.rent have NONE
of the 9 Aug work. There is also an open branch,
`feature/delivery-events`, carrying an accepted D0 and a temporary
capture endpoint.

Everything is pushed to origin, including the branch and the tags
`pre-attachments` / `pre-delivery-events`. Nothing is on `master`, so
nothing is deployed.

**➡ START HERE: `release-attachments-and-capture.txt`** — the detailed
release + test checklist for both. Release 1 (attachments) is a real
feature release and stays. Release 2 (event capture) is temporary and
comes back off. Do them in order, not together.

**dotrent is RETIRED** (1 Aug). Live ledger entries are now **gafol,
renters.rent, main** — the end state the housekeeping plan was aiming at,
bar recording the old Windows prod box's retirement.

---

## Parked state — read this first

The silence/email model (Phases 1–5, D1–D16) is complete, merged and
running live on renters.rent. Cron, outbound and inbound are all proven on
the real box. PWA live. Private-beta registration gate live and locked.

**Do not re-diagnose these — both are closed and root-caused:**
- The 4 Jul "verification email sends nothing, logs nothing" issue was
  **duplicate `.env` keys** (within one file Laravel takes the LAST `KEY=`
  and silently ignores earlier ones). Baked into the install recipe, Step 8.
- Snag **#23**, the exposed Mailgun credentials, is **rotated and proven**
  (2 Aug, a live landlord reply bound onto a case against the new signing
  key). Closed after 28 days.

**Authoritative record of what is deployed where: `docs/environment-state.md`.**

---

## What landed 1–2 Aug

**Deployed to both boxes (`7507a72`, code-only, no migrations).** Prod
jumped three weeks in one hop — it had never carried the 24 Jul onboarding
merge — so it took the onboarding hub + content refresh, the 27 Jul
usability pass, and the email fix together.

- **Email case normalisation (the only behavioural code change).** Breeze's
  `lowercase` rule *validates* for lowercase rather than applying it, so
  `Charlie@Example.com` was REJECTED with "The email field must be
  lowercase". Registration and profile-update now trim + lowercase before
  validation. **Proven on prod**: mixed case keyed in, stored lowercase.
  Three tests, each confirmed to fail without the fix.
- **UI:** admin unverified-users list + "Verified On"; nav split into
  Properties / Cases; post-verification welcome banner (**broken — see #37**).
- **DNS (1 Aug):** apex SPF, apex DMARC, tightened `_dmarc.mg` (dropped
  `ruf`/`fo` — forensic reporting forwards tenant data to third parties).
  Before/after in `docs/DNS records old values.txt`.
- **Mailgun:** credentials rotated; open/click tracking confirmed **off**
  in all three cases (tracking rewrites links, so an enabled tracker means
  the letter the landlord received is not the letter we hold as evidence).
- **Mail identity audit** → snags #31–#36; #8 closed as a duplicate of #25.

---

## What landed 9 Aug (all local + origin, NOT deployed)

**Attachment policy — merged to `main`, 570 tests.** Letter 1 carries
tenant-chosen photos under an admin-configurable ceiling (0–3, ships at
**1**); per-file 2MB → 4MB; the form now advertises the limit the SERVER
will accept, not ours; photos listed with sizes on the create form,
preview and case page. Chasing letters never attach — unchanged
behaviour, now deliberate. Design: `attachment-policy-design.md`.
Report: `attachment-policy-implementation-report.md`.

Four defects found while building it, three of them pre-existing:
- **#46** — the Edit round-trip **silently dropped staged photos**. Worst
  of the set: attach, preview, spot a typo, Edit, fix a word, resubmit —
  the letter went WITHOUT the photo, while the form said "your photo is
  saved". The old test `assertSee('photo is saved')` passed throughout
  because it pinned the *cue*, not the behaviour the cue claimed.
- **#45** — attachment rows written for files the 24h sweep had deleted.
- **#43** — a second Browse silently discarded the first photo (a file
  input replaces its whole FileList). **No test can ever catch this** —
  it lives entirely in the browser.
- Oversize input costing the whole selection.

**Delivery events (#25) — D0 ACCEPTED, on `feature/delivery-events`.**
Nine rulings taken; see the RULINGS section of
`delivery-failure-design-question.md` and **D17** in the design doc.
Net effect: #25 touches **neither the escalation counter nor D3**, and no
longer depends on #24.

Two build steps done, both safe:
- **#47 closed** — case status handling was OPT-OUT in two places, so a
  new enum value defaulted to "sweep it and run a clock on it". Now an
  exhaustive `match` with no default; verified the guard fires by
  temporarily adding an unclassified status.
- **D17** added, so the authoritative doc no longer silently assumes
  delivery.

**Gated:** the receiver is NOT written. Charlie ruled a real prod send
comes first (D0.2 found our signature middleware probably rejects the
delivery-event payload shape, and its 406 makes Mailgun stop retrying —
silently). Release 2 in the checklist captures the real bytes.

**Mailgun dashboard checked 9 Aug:** no delivery-event webhooks exist at
either level; Unsubscribes OFF; click + open tracking OFF and **must stay
off** (click tracking rewrites links at send time, so the delivered
letter would no longer match the frozen `body_raw`). Two hard bounces
sitting in Suppressions since early July that the platform never knew
about — #25 in the wild, not hypothetical.

**New snag #48** — `admin@renters.rent` cannot receive mail, and it is
BOTH the published privacy/cookies contact AND the admin login, so admin
password reset is broken. Fix is an `info@renters.rent` mailbox. Not
started.

---

## Open actions — do these first

**FIRST: work `release-attachments-and-capture.txt`.** Two releases,
in order, with the full test list. Everything below predates 9 Aug and
is still outstanding unless marked otherwise.

**Asked for but NOT confirmed done. Check before trusting.**

1. **Restore prod pacing.** Surface B was set to `interval_days=1` /
   `max_notices=2` for the ladder test. Production defaults, confirmed
   against BOTH the seeder and the design doc: **`interval_days` = 14,
   `max_notices` = 4.** Everything else is already at default; B2 "Applies
   to In-flight cases" stays **No**.
2. **Close out case 3.** B2 is Off, so the settings change above will NOT
   slow it — it keeps its frozen `0/1 days` snapshot and carries on
   laddering. Abandon or resolve it directly. Check `silence_shadow_log`
   for any OTHER case created during testing carrying a 1-day snapshot.
3. **Confirm the allowlist.** On the box: `php artisan config:cache` then
   `php artisan config:show app`. Her address the only entry;
   `registration_open_to_all` still **false**. Also `grep -c` for duplicate
   `KEY=` lines while in there — that is the trap that broke it before.
4. **Settle #27's 403.** Read `storage/logs/laravel.log` on prod at the
   time of the failure. `AuthorizationException` = id mismatch;
   `InvalidSignatureException` = the link itself was rejected. **Different
   causes need different fixes** — don't design before this is known.
5. ~~**Read the mail headers.**~~ **DONE 2 Aug — the 1 Aug DNS change is
   now proven ALIGNED.** Landlord letter (→ Outlook) and tenant
   notification (→ Gmail) both read from headers: SPF, DKIM and DMARC all
   pass, all on `mg.renters.rent`, and Microsoft scored the landlord letter
   `compauth=pass reason=100` / `SCL:1` / `BCL:0` — inbox on its merits.
   DKIM selector is **`s1`**. Full detail in `environment-state.md`.
   *Residual:* the Contact Us reply (apex sender, #32) is still unread —
   expected to pass on relaxed alignment, but that is reasoning, not a
   reading. And no attachment-bearing letter has ever been spam-scored.

**Never recorded:** the outcome of the July escalation-ladder test on case
3. If it is still wanted as evidence, get it from `silence_shadow_log`
before wiping; otherwise close it out and let it go.

*Read the clock correctly before calling the sweep broken:* it is a daily
**06:15 batch** and silence floors to **whole days**, so a 1-day interval
fires ~34h after the letter, not 24h; later rungs drift one sweep. Designed
behaviour — harmless at the real 14-day interval.

**Then: begin the family trial.** The front door is locked, mail is proven
both directions, and the email fix that blocked a capitalised address is
live. gafol stays permanent staging.

---

## Priorities after the trial opens

- **#25 — no delivery-failure detection.** A bounced letter and an ignored
  letter are indistinguishable, so the product will say "served on the 12th,
  no response in 14 days" with full confidence when nobody was ever served.
  Goes to the core claim. ~a day of plumbing, but the DESIGN questions come
  first — `docs/delivery-failure-design-question.md` is **awaiting an
  outside review; do not build before it lands.**
- **#37 — the welcome banner has never worked.** Shipped 1 Aug, failed on
  the first real registration. Cheap fix (flash to session, not a query
  flag) and it needs the test that was missing.
- **Landlord-contact model (D0 candidate)** —
  `docs/landlord-contact-model-gap.md`. Property-owned, versioned contact
  with change history; retire the global email-keyed `landlord_contacts`;
  ONE address per property. ~5–6 focused days. **Sequenced after #25**,
  which makes a typo visible in the first place.
- **#32 → #33** — move `ContactReply` off the hardcoded apex sender; then
  the apex SPF added 1 Aug becomes unnecessary and can tighten to
  `v=spf1 -all`. Fixing #32 also removes Gmail's "on behalf of" display.

**Go-live switch (LATER, the only one):** `REGISTRATION_OPEN_TO_ALL=true`
+ `config:cache` on renters.rent. Public launch still gated by solicitor
wording sign-off (`pre-flip-checklist.md`). That sign-off does **not** gate
the family trial — Charlie's call, 21 Jun.

---

## Read in this order

1. **/CLAUDE.md** — working agreements. Carries the **Migrations** rule
   (manual MariaDB check before merge) and the **Deployment-ledger** rule.
2. **docs/environment-state.md** — the ledger; current truth of what is
   deployed where. **Not reconciled against `migrate:status` since 27 Jun**
   — do that at the next deploy.
3. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design (D1–D16).
   Wins over any brief; tie-breaker if two docs disagree.
4. **docs/llcs-snagging-list.txt** — the pre-live-running to-do list.
5. **docs/huk-laravel-site-install-recipe.md** — the sibling-site build.
6. **docs/User Guides/** — dispatch-sequence references + automation
   orientation (read at leisure).

---

## Snags — open

**#1, #2, #7, #12, #13, #17, #18, #19, #22, #24, #25, #26, #27, #28, #29,
#30, #31, #32, #33, #34, #35, #36, #37, #38, #39*, #40*, #42, #47*, #48.**

\* = fixed in code on 9 Aug but **not yet deployed** — #39 (preview shows
no attachments), #40 (photo limits), #43, #44, #45, #46 all ship with
Release 1; #47 ships with the delivery branch.

Closed since: **#23** (creds rotated, 1 Aug), **#8** (duplicate of #25,
1 Aug — retired, not fixed), **#41** (9 Aug — the Plesk PHP Settings page
was inert because the domain uses CloudLinux **PHP Selector**; limits now
8M/16M, verified from phpinfo AND functionally). Resolved by Phase 5
(D16): #4, #14, #15, #16, #20, #21.

**Added 9 Aug (attachment build + walk):** **#43** sequential photo
selection silently discarded earlier files — browser-side, no test can
catch it; **#44** an abandoned draft told a NEW case its photos were
saved, talking the tenant out of attaching evidence; **#45** attachment
rows written for files the 24h sweep had deleted; **#46** the Edit
round-trip silently dropped staged photos on the most ordinary path
through the form; **#47** case status handling was opt-out, so a new
status defaulted to being swept and clocked; **#48** `admin@renters.rent`
cannot receive mail — published privacy contact AND admin login.
#43–#47 are fixed and awaiting deploy. **#48 is not started.**

**Standing lesson from 9 Aug, worth keeping:** #46 survived because a
test asserted the *cue* ("photo is saved") rather than the behaviour the
cue claimed. A green suite can be testing the lie. Same family as #26.

**Added 1 Aug (mail identity audit):** #31 `deploy-checklist.md` names
`inbox.renters.rent`, a domain with no DNS — a rebuild following it would
silently break every landlord reply, and the `CaseNotice` guard tests
presence not validity; #32 `ContactReply` hardcodes an apex sender; #33
apex SPF is a temporary shape, blocked on #32; #34 CLAUDE.md's Mail section
contradicts the code (the keys have no defaults, by design); #35 local
`MAIL_FROM_ADDRESS` is a third-party domain; #36 Mailgun tier — open
question, two pre-sales questions before spending. Four are minutes of doc
or config work; only #32 is code.

**Added 2 Aug (prod verification run):** **#37** — post-verification
welcome banner never fires (`redirect()->intended()` discards the
`?verified=1` fallback whenever a `url.intended` exists). **#27 gained a
worse failure mode** — a hard 403, not the /login redirect recorded; root
cause OPEN, see action 4 above. **#32 gained the visible symptom** — Gmail's
"on behalf of" display.

Deferred named gaps (not built): `letter_templates.active` toggle,
`ExhaustedStance` enum/`setStance`. Both candidates for a future Surface-D
admin pass.

---

## Doc status map (design doc + ledger win when in doubt)

**LIVE — trust these:** `CLAUDE.md`; `environment-state.md`;
`llcs-silence-model-design.md` (authoritative); `llcs-snagging-list.txt`;
`huk-laravel-site-install-recipe.md`; `DNS records old values.txt` (DNS
before/after + Mailgun config decisions); `landlord-contact-model-gap.md`
(D0 candidate, 27 Jul); `delivery-failure-design-question.md` (**awaiting
outside verdict — do not build #25 first**); `pre-flip-checklist.md`;
`User Guides/`.

**HISTORICAL — accurate for their phase, don't lead with them:**
`d16-cc-brief.md`, the D14/D15 briefs/reports/runbooks, the
phase-1/2a/2b/3 briefs + runbooks + write-ups, `dotrent-deploy-plan.md`
(Phases A/B history; Phase C SUPERSEDED).

**ARCHIVE — ignore for current work:** `LLCS Version 1/`,
`LLCS old docs 3 May 1150/`, `landlord-contact-service-*.md`.

**VERIFY before relying on:** `phase-3-design-*.md`,
`phase-8-design-notes.md`, **`deploy-checklist.md` (contains the known-bad
`inbox.renters.rent` value — snag #31)**, `huk-*`, `chats/*`,
`state-summary-2026-05.md`, `session-writeup-*`.

---

## Branches

**⚠ ONE OPEN BRANCH: `feature/delivery-events`** (on origin). Holds the
accepted D0, the #47 fix, D17, and a TEMPORARY capture endpoint. Do not
merge it — the receiver it exists for is not written yet, and the capture
code is meant to come back out. Tag `pre-delivery-events` on main.

`feature/attachment-policy` merged to `main` (`--no-ff`) 9 Aug; tag
`pre-attachments`; deletable.

`feature/admin-unverified-users` merged to `main`
(`7507a72`, `--no-ff`) 1 Aug and deployed to both boxes 1–2 Aug; deletable.
`feature/onboarding-nav` merged (`b92b907`); deletable.

Merged, retained-but-deletable: `registration-lock`,
`d14-escalation-exhausted`, `d15-engagement-gating`, `d16-admin-config-ui`,
`d16-admin-security`, `feature/pwa-wiring`.

Tags on origin: `pre-registration-lock` (`a63ac4a`), `post-d16-phase5`
(`cf2f5c9`), `pre-d16-phase5`, `pre-d16`.

---

## Maintenance rule

When a phase closes: move its brief/report/runbook to HISTORICAL, repoint
the parked-state block, prune resolved snags. Keep this file to one screen.
On any deploy, the LAST step is writing `environment-state.md`.

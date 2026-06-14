# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has ~28 files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-06-14 (D14 / `escalation_exhausted` closed).
**Parked state:** `main = 61defc6` (D14 merge, `--no-ff`); pre-merge tag
`pre-d14` → `b20061b` (on origin). gafol D14 live-fire passed; suite 485.
Open snags #4, #7, #8, #12–#21 (#21 NEW — duplicate abandon controls on the
exhausted page; machine correct, UI confusing; carries a design question
for Charlie). Pre-flip conditions: the Phase 3 three PLUS the D15
`escalation_authorisation` sign-off PLUS the D14 exhaustion-wording sign-off
(closer + tenant notice + signposting). `d14-escalation-exhausted` and
`d15-engagement-gating` branches retained (delete after solicitor sign-off
+ dotrent deploy).

**The silence/email cycle is now complete** — every terminal outcome has a
home. Next substantive work is Phase 5 (admin UI) or the snag batch; nothing
is blocked.

---

## Read in this order (the live set)

1. **/CLAUDE.md** (repo root) — working agreements. Standing rules.
2. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design.
   Wins over any brief, and is the tie-breaker if two docs disagree.
3. **docs/d14-escalation-exhausted-writeup-Sun-2026-06-14-0539pm.md** —
   the latest close-out. Best single "where are we" doc: what D14
   shipped (the `escalation_exhausted` terminal, the one-shot landlord
   closer, both revival edges, the label-only stance), the gafol
   live-fire results, snags #21/#20, the exhaustion-wording pre-flip
   obligation, and the open thread. Lead with this. The D15 close-out
   (`d15-engagement-gating-writeup-…`) is the prior close-out — read it
   for the engagement-gating model and the `escalation_authorisation`
   pre-flip condition it still carries.

Then **one** of these by chosen path:
- **Phase 5 — admin UI** for templates + settings. Next substantive
  phase; unscoped. First deliverable would be D0 (report, no code) per
  CLAUDE.md.
- **Snag batch** — **docs/llcs-snagging-list.txt** (open snags
  #12–#21, each with a one-line Fix; #21 carries a design question).
- **Short-references mini-batch** — snag **#4** in the same snagging
  list (design already decided there).

That's the minimum to pick up cold.

---

## Doc status map (best-effort — design doc + latest close-out win)

This classification is by era/filename and what this session touched.
It is NOT infallible. When in doubt, trust the AUTHORITATIVE design
doc and the newest close-out over anything below.

**LIVE — trust these:**
- `CLAUDE.md` (root) — working agreements.
- `llcs-silence-model-design.md` — authoritative design (now incl. D15
  which supersedes D7, and the D5 "Implementation note — Phase 4 / D14").
- `d14-escalation-exhausted-writeup-Sun-2026-06-14-0539pm.md` — current
  state / latest close-out.
- `llcs-snagging-list.txt` — open snags #4, #7, #8, #12–#21.
- `pre-flip-checklist.md` — governs the production (renters.rent)
  cutover; the Phase 3 three pre-flip conditions PLUS the D15
  `escalation_authorisation` sign-off PLUS the D14 exhaustion-wording
  sign-off (closer + tenant notice + signposting) live here in spirit.

**HISTORICAL — accurate for their phase, do NOT lead with them.**
Once a phase closes its brief/report/runbook drop to this tier:
- `d14-...` brief/reports/runbook — `cc-brief-d14-phase4.md`,
  `cc-report-d14-d0.md`, `cc-report-d14.md`,
  `gafol-live-fire-runbook-d14.md` — D14, superseded as an entry point by
  the close-out above.
- `d15-engagement-gating-writeup-Sat-2026-06-13-0103pm.md` — prior
  close-out; the engagement-gating model + the `escalation_authorisation`
  pre-flip condition.
- `d15-...` brief/reports — `cc-brief-d15.md`, `cc-report-d15-d0.md`,
  `cc-report-d15.md`, `authorisation-required-nudge-draft.md` — D15.
- `silence-phase-3-writeup-Sun-2026-06-07-0456pm.md` — Phase 3 close-out;
  still the source for the Phase 3 pre-flip conditions.
- `cc-brief-silence-phase-3.md`, `cc-report-silence-phase-3.md`,
  `gafol-live-fire-runbook-3.md` — Phase 3.
- `cc-brief-silence-phase-1.md`, `-2a.md`, `-2b.md`,
  `gafol-live-fire-runbook-2b.md`,
  `silence-phase-2b-writeup-Sun-2026-06-07-0602am.md` — earlier
  phases.

**ARCHIVE — pre-silence-model LLCS era; ignore for current work:**
- `LLCS Version 1/` (whole folder).
- `LLCS old docs 3 May 1150/` (whole folder).
- `landlord-contact-service-design.md`,
  `landlord-contact-service-implementation-plan.md` — the original
  LLCS design, predates the silence-model rewrite.

**VERIFY before relying on — not read/confirmed this session:**
- `phase-3-design-d13-addendum.md`, `phase-3-design-doc-update.md` —
  likely folded into `llcs-silence-model-design.md`; if so they're
  historical. Treat the design doc as canonical.
- `phase-8-design-notes.md` — forward/speculative; not current scope.
- `deploy-checklist.md` — older/general deploy notes; the LIVE
  cutover doc is `pre-flip-checklist.md`. Confirm which applies.
- `huk-laravel-site-install-recipe.md`,
  `huk-laravel-site-install-recipe.md`'s chat copy under `chats/`,
  `chats/2026-05-30-*` — HUK/ukrenters staging install reference,
  env-specific.
- `state-summary-2026-05.md`, `session-writeup-sat-2026-06-06.md`,
  `chats/README.txt` — dated session records; historical.

---

## Maintenance rule

When a phase closes: move its brief/report/runbook to HISTORICAL in
the map above, and repoint step 3 to the new close-out write-up.
Keep this file to one screen — it's a router, not a record.

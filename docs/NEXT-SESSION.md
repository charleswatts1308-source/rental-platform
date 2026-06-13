# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has ~28 files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-06-13 (D15 closed).
**Parked state:** `main = b4829fd` (D15 merge, `--no-ff`); pre-merge tag
`pre-d15` → `52ecad2` (on origin). gafol D15 live-fire passed. Open snags
#4, #7, #8, #12–#20. Pre-flip conditions: the Phase 3 three PLUS the D15
`escalation_authorisation` solicitor sign-off. `d15-engagement-gating`
branch retained (delete after solicitor sign-off + dotrent deploy).

---

## Read in this order (the live set)

1. **/CLAUDE.md** (repo root) — working agreements. Standing rules.
2. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design.
   Wins over any brief, and is the tie-breaker if two docs disagree.
3. **docs/d15-engagement-gating-writeup-Sat-2026-06-13-0103pm.md** —
   the latest close-out. Best single "where are we" doc: what D15
   shipped (engagement-gated escalation, two-class model), the gafol
   live-fire results, snags #20/#12, the `escalation_authorisation`
   pre-flip obligation, and the open thread. Lead with this. The Phase 3
   close-out (`silence-phase-3-writeup-…`) is the prior close-out — read
   it only for the Phase 3 pre-flip conditions it still carries.

Then **one** of these by chosen path:
- **Phase 4 (`escalation_exhausted`)** — now unblocked (D15 landed
  first). No brief exists yet; first deliverable is D0 (report, no code)
  per CLAUDE.md. Source material: the design doc's exhausted-intent
  section (D5) + the D15 close-out's open thread.
- **Snag batch** — **docs/llcs-snagging-list.txt** (open snags
  #12–#20, each with a one-line Fix).
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
- `llcs-silence-model-design.md` — authoritative design (now incl. D15,
  which supersedes D7).
- `d15-engagement-gating-writeup-Sat-2026-06-13-0103pm.md` — current
  state / latest close-out.
- `llcs-snagging-list.txt` — open snags #4, #7, #8, #12–#20.
- `pre-flip-checklist.md` — governs the production (renters.rent)
  cutover; the Phase 3 three pre-flip conditions PLUS the D15
  `escalation_authorisation` solicitor sign-off live here in spirit.

**HISTORICAL — accurate for their phase, do NOT lead with them.**
Once a phase closes its brief/report/runbook drop to this tier:
- `d15-...` brief/reports — `cc-brief-d15.md`, `cc-report-d15-d0.md`,
  `cc-report-d15.md`, `authorisation-required-nudge-draft.md` — D15,
  superseded as an entry point by the close-out above.
- `silence-phase-3-writeup-Sun-2026-06-07-0456pm.md` — prior close-out;
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

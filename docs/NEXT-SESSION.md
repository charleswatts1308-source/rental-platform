# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has ~28 files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-06-07 (Phase 3 closed).
**Parked state:** `main = bc743e9`; gafol on `main` at `bc743e9`
(composer install run). Open snags #12–19. Three pre-flip conditions
recorded (see the Phase 3 close-out).

---

## Read in this order (the live set)

1. **/CLAUDE.md** (repo root) — working agreements. Standing rules.
2. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design.
   Wins over any brief, and is the tie-breaker if two docs disagree.
3. **docs/silence-phase-3-writeup-Sun-2026-06-07-0456pm.md** — the
   latest close-out. Best single "where are we" doc: parked state,
   what Phase 3 delivered, the three pre-flip conditions, and the
   "Open thread for Phase 4+". Lead with this, not the pre-merge docs.

Then **one** of these by chosen path:
- **Phase 4 (`escalation_exhausted`)** — no brief exists yet; first
  deliverable is D0 (report, no code) per CLAUDE.md. Source material:
  the design doc's exhausted-intent section + the close-out's open
  thread.
- **Snag batch** — **docs/llcs-snagging-list.txt** (open snags
  #12–19, each with a one-line Fix).
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
- `llcs-silence-model-design.md` — authoritative design.
- `silence-phase-3-writeup-Sun-2026-06-07-0456pm.md` — current state.
- `llcs-snagging-list.txt` — open snags #4, #7, #8, #12–19.
- `pre-flip-checklist.md` — governs the production (renters.rent)
  cutover; the three pre-flip conditions live here in spirit.

**HISTORICAL — accurate for their phase, do NOT lead with them.**
Once a phase closes its brief/report/runbook drop to this tier:
- `cc-brief-silence-phase-3.md`, `cc-report-silence-phase-3.md`,
  `gafol-live-fire-runbook-3.md` — Phase 3, now superseded as an
  entry point by the close-out.
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

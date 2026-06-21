# NEXT-SESSION — start here

Living entry point for a fresh session. Stable filename; keep it
current. The `docs/` folder has ~28 files and many are stale — this
index says which to trust and which to ignore so you don't re-derive
state from a superseded doc.

**Last updated:** 2026-06-21 (Phase 5 / D16 Admin/Config UI merged).
**Parked state:** `main = 0174664`; Phase 5 merge commit `cf2f5c9`
(`--no-ff`). Tag triplet on this phase: `pre-d16` (§0 base) →
`pre-d16-phase5` (surfaces base) → `post-d16-phase5` (merged). Suite
**535 passing / 2226 assertions** (was 485 at the D14 close).

**Phase 5 (D16 Admin/Config UI) — BUILT AND MERGED.** § 0
admin-security hardening (`is_admin` out of `$fillable` + regression
test) **plus** the three surfaces: **A** template editor (version
history `letter_text_change_history`, live-whitelist token validation,
mandatory preview), **B** settings editor (range-reject, the
`escalation.apply_inflight` flag shipping **Off**, audit log
`settings_change_hist`), **C** read-only case oversight + `case_events`
trail. Also folded in: the shared state-aware **display predicate**
(`showsNextEscalation`/`showsHoldUntil` on `RepairCase`), the **#4**
short reference format (6-char, A–Z+2–9 minus I/O/0/1), and snags
**#16/#20/#21**. MariaDB schema verified on dev (no #18 ON UPDATE trap;
indexes + FKs correct; clean migrate/rollback).

**The silence/email cycle remains complete** and the admin/config layer
now sits on top of it. Nothing is blocked. Next substantive work is the
**pre-flip path** or the **remaining snag batch** (see chooser below).

---

## Read in this order (the live set)

1. **/CLAUDE.md** (repo root) — working agreements. Standing rules.
   (Now also carries a **Migrations** rule: tests run SQLite, dev/prod
   run MariaDB → any table create/alter gets a manual MariaDB
   `SHOW CREATE TABLE` check before merge.)
2. **docs/llcs-silence-model-design.md** — AUTHORITATIVE design.
   Wins over any brief, and is the tie-breaker if two docs disagree.
   Now carries **D1–D16** (D16 = Phase 5 Admin/Config UI, incl. the
   #21 Option C ruling — abandon-collision resolved, D14 revival
   preserved).
3. **Latest close-out** — the newest phase narrative is the D14
   write-up `d14-escalation-exhausted-writeup-Sun-2026-06-14-0539pm.md`
   (with the D15 close-out before it). **Phase 5 (D16) has no separate
   close-out doc** — its record is the design doc § D16, the brief
   `d16-cc-brief.md`, and merge commit `cf2f5c9`. For "what shipped in
   Phase 5," read § D16 + that commit.

Then **one** of these by chosen path:
- **Pre-flip path** — the production (renters.rent) cutover. Conditions:
  the Phase-3 three (dotrent real inbound round-trip on the promoted
  domain, create-case attachment over prod Mailgun, cases-empty check)
  PLUS the D15 `escalation_authorisation` sign-off PLUS the D14
  exhaustion-wording sign-off (closer + tenant notice + signposting).
  Governed by **docs/pre-flip-checklist.md**.
- **Snag batch** — **docs/llcs-snagging-list.txt**. Open after Phase 5:
  **#7, #8, #12, #13, #17, #18, #19** (see below).

That's the minimum to pick up cold. (Phase 5 is no longer a "next"
option — it's done.)

---

## Snags — post-Phase-5 status

**Resolved by Phase 5 (D16):** #4 (reference format), #14 + #15
(display predicate), #16 (live `max_notices` denominator), #20 (D9
dark-mode header), #21 (abandon-collision → Option C).

**Still open:** #7 (create-case landlord lookup/auto-fill), #8
(delivery-status webhooks — its own pre-flip evidential-hardening
phase, NOT an admin panel), #12 (seed-data realism), #13 (age-clock
gauge label), #17 (`dev:reset` schema-guard for partial migrate),
#18 (implicit `ON UPDATE CURRENT_TIMESTAMP` trap — the four
pre-existing tables; Phase 5's two new tables were built clear of it),
#19 (attachments on tenant replies).

**New deferred items surfaced this session (named gaps, not built):**
- **`letter_templates.active` toggle** — deferred, stays phpMyAdmin-only.
  `active` is load-bearing on the sweep (`forEscalation` /
  `firstActiveOfType`); deactivating mid-escalation has undesigned
  in-flight semantics. Candidate for the post-#8 machine-state-UI
  ruling. (Recorded in design doc § D16 "Explicitly NOT Phase 5".)
- **`ExhaustedStance` enum + `setStance` action** — left dormant in the
  codebase (no UI) after the #21 Option C dropdown removal. Future
  disposition deferred; reversed nothing on the backend.

---

## Doc status map (best-effort — design doc + latest close-out win)

This classification is by era/filename and what recent sessions
touched. It is NOT infallible. When in doubt, trust the AUTHORITATIVE
design doc and the newest close-out over anything below.

**LIVE — trust these:**
- `CLAUDE.md` (root) — working agreements (now incl. the Migrations rule).
- `llcs-silence-model-design.md` — authoritative design (D1–D16).
- `d14-escalation-exhausted-writeup-Sun-2026-06-14-0539pm.md` — latest
  phase close-out (D14; D15 narrative in the prior write-up).
- `llcs-snagging-list.txt` — open snags #7, #8, #12, #13, #17, #18, #19.
- `pre-flip-checklist.md` — governs the production cutover; the Phase-3
  three PLUS the D15 `escalation_authorisation` PLUS the D14
  exhaustion-wording sign-off live here in spirit.

**HISTORICAL — accurate for their phase, do NOT lead with them:**
- `d16-cc-brief.md` — Phase 5 build brief (folded into design doc § D16).
- `d14-...` / `d15-...` briefs, reports, runbooks, and the D15 close-out
  write-up — earlier phases.
- `silence-phase-3-writeup-...`, `cc-brief-silence-phase-3.md`,
  `gafol-live-fire-runbook-3.md`, and the phase-1/2a/2b briefs +
  runbooks + write-ups — earlier phases.

**ARCHIVE — pre-silence-model LLCS era; ignore for current work:**
- `LLCS Version 1/`, `LLCS old docs 3 May 1150/` (whole folders).
- `landlord-contact-service-design.md`,
  `landlord-contact-service-implementation-plan.md` — original LLCS
  design, predates the silence-model rewrite.

**VERIFY before relying on — not re-confirmed recently:**
- `phase-3-design-d13-addendum.md`, `phase-3-design-doc-update.md` —
  likely folded into the design doc; treat the design doc as canonical.
- `phase-8-design-notes.md` — forward/speculative; not current scope.
- `deploy-checklist.md` — older/general; the LIVE cutover doc is
  `pre-flip-checklist.md`.
- `huk-laravel-site-install-recipe.md`, `chats/*`,
  `state-summary-2026-05.md`, `session-writeup-*` — env-specific /
  dated session records; historical.

---

## Branches

Merged and retained-but-deletable (kept for solicitor sign-off +
dotrent deploy): `d14-escalation-exhausted`, `d15-engagement-gating`,
`d16-admin-config-ui`, `d16-admin-security`. Delete once the pre-flip
sign-offs land.

---

## Maintenance rule

When a phase closes: move its brief/report/runbook to HISTORICAL in
the map above, repoint step 3 / the parked-state block to the new
state, and prune resolved snags. Keep this file to one screen — it's a
router, not a record.

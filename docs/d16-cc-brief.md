# Phase 5 (D16 — Admin / Config UI) — CC build brief

**Authority:** `docs/llcs-silence-model-design.md` § D16 (final, ruled 21 Jun 2026). This brief is the build instruction; the design doc is the authority for *why*. Where this brief and the doc agree, build it. If you believe they conflict, stop and report — do not reconcile silently.

**Report-first.** Before writing migrations or code, report back: (1) your build order, (2) the two history-table migration shapes (columns + types), (3) the snake_case table identifiers you intend to use, (4) anything in the "resolve at build" list below that turns out to be a two-step rather than a one-liner. Wait for the go before implementing. Standard CLAUDE.md report-first discipline applies throughout.

**Branching.** Feature branch per CLAUDE.md conventions. The admin-security hardening (below) is a *separate* commit/branch from the Phase 5 build — keep them apart.

---

## 0. Precondition commit — admin-security hardening (NOT part of Phase 5)

Independent of Phase 5; land it first because it is small and isolated, but it is its own commit, not folded into the surfaces.

- Remove `is_admin` from `User` `$fillable` (latent mass-assignment / privilege-escalation vector; not currently exploited, but must not be fillable). Set `is_admin` explicitly in the dev/seed commands that currently rely on array-create (`DevReset`, any seeder) — e.g. `$user->is_admin = true; $user->save();` or `forceFill`. No behaviour change intended.
- Add a regression test: a non-admin authenticated user receives 403 on an `/admin/*` route, **and** `is_admin` does not change after a crafted profile-update POST carrying `is_admin=1`.
- Correct the stale `web.php:88` comment ("Admin routes (user ID 1 only)") — access is purely the `is_admin` column now; no id check, no env check.

Report when done; this is the green light that the gate Phase 5 sits behind is clean.

---

## What you are building

Three admin surfaces (A, B, C), all behind the **existing** admin route group (`auth + verified + admin` per `web.php:89` / `AdminMiddleware`). You are **adding routes behind a gate that exists** — you are not building auth. Do not add a second auth mechanism; reuse the admin middleware/group.

Several snags are folded in by shared root (see each surface). Anything not named here is out of scope — see § "Not in this work".

---

## Surface A — Template editor (`letter_templates`)

A form to edit master letter wording, replacing phpMyAdmin.

- **A1 — always editable, no lock** (including solicitor-reviewed rows). Every save writes a row to the **letter text change-history** table. Shape: template ref, version, editor (user id), timestamp, before-text, after-text (full letter bodies, TEXT). Versioned. This table is the trace that reconciles "always editable without a release" with "evidential".
- **A2 — token validation blocks the save.** Reject a save whose text drops or misspells any placeholder against the renderer's placeholder whitelist (catch dropped tokens, misspellings like `{{issue_desciption}}`, and malformed braces like `{ {notice_number}}`). **Read the whitelist live from the renderer at validation time — do not copy it into the validator** (copying drifts the first time a placeholder is added). Validation is template-save-time only; it never touches a case.
  - *Possible two-step — report if so:* if the renderer does not currently expose its placeholder whitelist as something the validator can read, the minimal refactor to expose it comes first. Flag this in your build-plan report rather than copying the list.
- **A3 — mandatory rendered preview before the edit goes live.** Reuse `LetterTemplateRenderer`. Not optional.
- **Invariant (write a test):** a template edit affects **future sends only**. Letters already in `case_messages` must be untouched by any template edit. The editor must never reach into sent correspondence.
- **#20 (folded):** if the preview renders through D9's `buildHeaderBlock`, the dark-mode invisibility bug appears in the preview too. Fix `buildHeaderBlock` once; it covers both the D9 header and the Surface A preview.

---

## Surface B — Settings editor (`settings`)

A form to edit intervals, caps, ladder lengths.

- **B1 — hard reject out-of-range values** on save (e.g. `escalation.interval_days = 0`, `escalation.max_notices = 0`). Reject, do not soft-warn — a setting that stalls the sweep is a production incident.
- **B2 — "Applies to In-flight cases" flag, ships OFF.** A single global boolean stored in the settings table, human-readable key "Applies to In-flight cases", default **No (Off)**.
  - **Off (the shipped default):** interval changes apply to **new cases only**; cases already mid-clock keep the interval they started under. This is the clean-observability default the family pilot needs — do not ship it On.
  - **On (flag flipped):** the sweep reads thresholds live, so a changed interval reaches in-flight cases at the next sweep. Build the behaviour so the flag *can* switch it on later without a code change, but it ships Off.
  - Test both flag positions with the time-injection convention (CLAUDE.md) — no real waiting.
- **B3 — settings change-audit, own table.** Every settings change writes one row to the **interval-settings-hist** table. Shape: setting key, editor (user id), timestamp, old-value, new-value — **old and new on the same row** (self-contained; a single row is the whole change, no deriving old-value from the prior row). Flat scalars, **no version column, no `subject_type` discriminator.** This is a *separate table* from Surface A's letter text change-history — do not merge them, do not build a polymorphic table.
- **#16 (folded — hard dependency of this surface):** the literal `"Stage N of 4"` hardcodes the denominator. The moment this editor can change `escalation.max_notices`, `4` silently lies. Replace the literal — read `escalation.max_notices` **live** wherever the denominator is displayed.

---

## Surface C — Case oversight (READ-ONLY)

Admin visibility: case state, ball, clock position, next-sweep projection, `case_events` trail. **Read-only. No force-transition. No case-field editing through the UI.** A force-transition UI is a hole in the evidential spine and is deliberately not built. (Break-glass for a stuck case stays manual via phpMyAdmin — the friction is the safeguard.)

- **State-aware-display predicate (folds #14 / #15 / #21-tail):** build **one** predicate that governs what a card/row displays per state, and use it everywhere — the existing tenant-facing displays *and* Surface C's projection. Do not let Surface C reinvent this. Its rules include:
  - `on_hold` shows no "Next escalation" (#14).
  - `hold_until` is not shown stale after release (#15).
  - **Terminal states show no "Next escalation" and no live actions** (#21-tail).
- **#21 — exhausted = dead = terminal (RULED, do not re-open).** An `escalation_exhausted` case renders **record-only**: its state and frozen record, **no live controls** — no "Abandon this case" action (a terminal case has nowhere to transition to; the action is absent, not disabled-and-shown), no live stance dropdown. Build no future-provision and no placeholder for post-exhaustion actions — none exists by design.
- **#4 — case reference (folded, build with this surface so the list shows the final format).** 6-character, uppercase, alphabet **A–Z + 2–9 excluding I, O, 0, 1** (32 chars). No profanity/near-word filter needed (digits mixed in). Regenerate existing references to this format.
  - *Assumption — confirm before regenerating:* only seed-data references exist pre-flip, so this is a regenerate, not a data migration of real references. If any real references exist, stop and report before touching them.

---

## The two history tables — explicit summary (so they are not confused)

| | letter text change-history (A1) | interval-settings-hist (B3) |
|---|---|---|
| Records | letter wording edits | settings value edits |
| Versioned? | **Yes** (version column) | **No** |
| Before/after | full letter bodies (TEXT) | scalars, old+new on one row |
| Columns | template ref, version, editor, timestamp, before-text, after-text | setting key, editor, timestamp, old-value, new-value |
| Discriminator | none | **none — do not add `subject_type`** |

Two separate tables. Snake_case identifiers are yours at build, following existing schema convention (e.g. `letter_text_change_history`, `interval_settings_hist`) — propose them in your build-plan report.

---

## Testing expectations

Phase 5 is largely feature/unit-testable without live-fire — it sends no mail (Surface A's preview renders but does not send). Specifically pin:

- Non-admin → 403 on each new `/admin/*` route (the gate is applied to the new routes, not just inherited-in-theory).
- A1: a template edit writes a correct change-history row; a sent letter in `case_messages` is unchanged by a template edit.
- A2: a save with a dropped/misspelled/malformed token is rejected; a clean save passes.
- B1: out-of-range settings rejected.
- B2: with the flag Off, an interval change does **not** move an in-flight case; with it On, it does (time-injected). Pin the shipped default is Off.
- B3: a settings change writes a correct old→new row.
- #16: the denominator reflects a changed `escalation.max_notices`, not a literal `4`.
- #21: an `escalation_exhausted` case exposes no live controls.
- Display predicate: `on_hold` and terminal states show no "Next escalation".

---

## Resolve at build (mechanical — no design judgement, but report in the build plan)

- The exact placeholder whitelist source — confirm the renderer exposes it; if not, the minimal exposure refactor (see A2) comes first.
- Per-table column names / types and the two snake_case table identifiers.

## Not in this work (do not build)

- `is_admin` `$fillable` hardening → § 0, separate commit.
- #8 delivery-status webhooks → own pre-flip evidential-hardening phase, not an admin panel.
- Tooling/CLI snags (#9, #10, #13, #17) → tooling pass.
- Tenant-facing UI/features (#1, #2, #7, #19) → tenant-UI track.
- #18 `ON UPDATE CURRENT_TIMESTAMP` trap → confirmed not triggered by `settings` / `letter_templates`; note and move on.
- Any admin force-transition or case-field editing → deliberately not built (Surface C is read-only).

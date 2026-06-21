## D16 — Admin / Config UI (Phase 5)

**Status:** designed (this pass), not yet briefed or built. Ruling session Sun 21 Jun 2026.

**Governing principle.** D16 is the operational payoff on the foundational razor — *words a tenant or landlord reads are rows; machine behaviour is code.* Phase 1 built the storage half (`letter_templates`, `settings`); editing those rows still requires a seeder change or a raw phpMyAdmin edit. Phase 5 gives the rows a proper editing surface so wording and interval changes never require a developer action.

A second line is drawn through the whole phase: **the admin edits the rules, never reaches into a case.** Changing a template or a setting alters machine behaviour going forward — a legitimate admin act. Reaching into a specific case's state or frozen record breaks the `case_events` evidential spine and is deliberately *not* given a UI (see Surface C).

---

### Surface A — Template editor (`letter_templates`)

A form to edit the master wording of letters, replacing phpMyAdmin (which is too crude for prose, and mishandles quotes/apostrophes without escaping knowledge).

- **A1 — Letter text is always admin-editable. No lock, including on solicitor-reviewed rows.** The solicitor sign-off is not a freeze; wording must always be changeable without a release. To make edits evidential rather than silent, **every edit is retained in a dedicated template-history table** (version, editor, timestamp, before→after) — not a boolean "drifted" flag. The history table is the record of how the wording-of-record changed over time. *Rationale:* an evidential product cannot have its letters silently altered with no trace, but neither can it require a code release to fix wording. Full history reconciles both.
- **A2 — Token validation blocks the save.** A save is rejected if the edited text drops or misspells a placeholder (e.g. `{{notice_numbers}}`, `{{issue_desciption}}`, `{ {notice_number}}`) against the known placeholder whitelist. *Rationale:* the edit is free-text typed by a human; a fat-fingered token would later render blank into live sends — the admin-introduced recurrence of the old blank-description defect (snags #3 / #11). Validation is at **template-save time only**; it never touches a case. By the time a token reaches a case it is already resolved and frozen in `case_messages` — there is no token left to misspell and no editing of it.
- **A3 — Mandatory rendered preview before a template edit goes live.** Reuses the existing `LetterTemplateRenderer`. Not optional.

**Invariant preserved:** a template edit affects **future sends only**. Letters already sent stay frozen in `case_messages`; the editor must never retroactively alter sent correspondence.

---

### Surface B — Settings editor (`settings`)

A form to edit intervals, caps, and ladder lengths.

- **B1 — Hard reject out-of-range values.** Values that would break the sweep (e.g. `escalation.interval_days = 0`, `escalation.max_notices = 0`) are rejected on save, not soft-warned. *Rationale:* a setting that stalls the sweep is a production incident; guard against the idiot mistake.
- **B2 — Interval changes apply live to in-flight cases, behind a soft global flag (default ON for the pilot).** Thresholds are read live at sweep time, so a changed interval reaches cases already mid-clock at the next sweep — which is what the family-pilot "shorten intervals for observable pacing" plan needs. The behaviour is wrapped in a global on/off switch so it can be disabled later. *Rationale (recorded explicitly):* it is not yet known whether mid-flight application aids observability or muddies test results. The flag lets the pilot answer that empirically rather than the answer being guessed now. Default ON so the pilot exercises it; flip OFF if it proves confusing.
- **B3 — Settings changes are audit-logged** (who, what, when, old→new). *Note:* this is the same shape as A1's template-history table; likely one mechanism serving both, decided at build time.

---

### Surface C — Case oversight (read-only)

Admin visibility into cases: state, ball, clock position, next-sweep projection, and the `case_events` trail. **Read-only. No force-transition, no case-field editing through the UI.**

- *Rationale:* every state change is supposed to have a recorded cause in `case_events`. A UI that lets an admin force a transition is a hole in the evidential spine. There is no demonstrated need for admin intervention yet, and each forced transition is a new way to corrupt a case's narrative or violate a machine invariant.
- **Break-glass:** a genuinely stuck case is adjusted in extremis via phpMyAdmin. This is accepted *because* it is rare and manual — the friction is the safeguard that stops case intervention becoming routine. Such an adjustment bypasses `case_events` and will leave no recorded cause; acceptable for a documented break-glass action, unacceptable as a routine UI affordance, which is the whole reason C is read-only.
- If the pilot later surfaces a real need, the minimal safe extension is per-case next-sweep timing only (never arbitrary transitions) — deferred, not built speculatively.

---

### Snags folded into Phase 5

These are addressed in the Phase 5 build because they are direct dependencies or share a root with a Phase 5 surface:

- **#16** — `"Stage N of 4"` hardcodes the denominator. **Hard dependency of Surface B:** the moment the settings editor can change `escalation.max_notices`, the literal `4` silently lies. Must read `escalation.max_notices` live.
- **#14 / #15 / #21-tail** — "Next escalation" shown on `on_hold`; stale `hold_until` after release; "Next escalation" reappearing on the abandoned card. One shared **state-aware-display predicate**; Surface C's next-sweep projection must use the same predicate or it reinvents this bug family. Fix together.
- **#20** — D9 header block invisible in dark mode (`buildHeaderBlock`). If Surface A's preview renders through that block, the same bug appears; fix once, covers both.
- **#4** — human-quotable case reference (6-char uppercase, human-safe alphabet). Surface C's case list displays references; also a free pre-flip change while only seed data exists. Do it with Surface C so the list shows the final format.

---

### Explicitly NOT Phase 5

- **#8 (delivery-status webhooks)** → **its own pre-flip evidential-hardening phase**, not an admin-UI panel. Rationale: with the D2 ruling that the **tenant** is notified of an undeliverable outcome (an undeliverable letter is the tenant's business — it directly breaks the "your landlord was notified, here's the proof" promise), #8 has grown a tenant-facing notification + act-on-it flow that is a design surface in its own right. That phase becomes the natural home for the other delivery/evidential issues expected to surface, and is the pre-flip companion to solicitor sign-off. Mechanism reuses the existing HMAC-verified inbound webhook pattern.
- **Tooling / CLI snags** (#9, #10, #13, #17) → dotrent / tooling pass.
- **Tenant-facing UI / features** (#1, #2, #7, #19, and the #21 abandon-collision ruling proper) → separate tenant-UI track. *(Note: the #21 cosmetic-vs-mechanical "abandon" ruling remains queued for Charlie per the D14 close-out; the two #21 blocks in the snag file conflict, and the "open design question" block is authoritative over the prematurely-written "RULING" block. D14 must not be doc-updated until that call is made.)*
- **#18** (implicit `ON UPDATE CURRENT_TIMESTAMP` trap) → confirmed **not** triggered by Phase 5's two write-tables (`settings`, `letter_templates` are not on the trap list). Note and move on.

---

### Open for the build D0 / CC brief (not ruled here)

- Whether A1's template-history table and B3's settings-audit log are one mechanism or two.
- The exact placeholder whitelist A2 validates against (source of truth: the renderer's existing whitelist).
- Per-table column names / migration shape for the history + audit tables.
- *Assumption to confirm at D0:* the `is_admin` boolean auth groundwork (replacing the hardcoded `id=13`) is already in place, so Phase 5 is screens, not gatekeeping.

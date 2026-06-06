# Session write-up — Sat 2026-06-06

Silence-model design completed (D5/D6 + revisions), Phase 1 briefed,
implemented by CC, merged, deployed to gafol, and verified with a clean
send/delivery reconciliation. Big session.

## Design phase closed

Resumed at D5 from the held session. All six decisions now agreed —
authoritative detail in docs/llcs-silence-model-design-*.md. Headlines:

- D5: `escalation_exhausted` state — clock stops permanently, tenant
  notified, signpost guidance (content = data), optional landlord
  closer via the active-template-row idiom (template table IS the
  on/off switch).
- D6: clock = time since latest tenant message; tenant follow-up
  restarts it.
- D3 revision: four-letter ladder retired. One generic wake-up letter
  per side, rendered with {{notice_number}}; fallback lookup rule
  (stage=N row if present, else stage=NULL generic) means graduated
  per-stage letters can be reintroduced later as data, no code change.
- The razor, stated and applied repeatedly: words a tenant/landlord
  reads = rows; what the machine does = code. Explicitly rejected
  soft-coding the workflow itself.
- Phase 2 split into 2a (clock in shadow mode alongside old ladder,
  logs only) and 2b (cutover + demolition) — careful path chosen.
- Phase 5 added: admin CRUD UI for templates/settings (textarea +
  preview + validation), gated on Phases 1–4 verified. phpMyAdmin
  until then.

## Git discipline for the rewrite

Tag `pre-silence-model` on main as rollback anchor. One branch per
phase, --no-ff merge to main after review + green suite + exploratory
pass, branch deleted, deploy from main only. No direct commits to main
during the rewrite.

## Phase 1 — briefed, built, accepted

Brief: docs/cc-brief-silence-phase-1.md. Hard stop at Deliverable 0
(report before edits) worked exactly as intended.

CC's D0 report found the one thing worth finding: Mail::queue()
re-renders the mailable at worker pickup, so the "frozen" body_raw and
the sent bytes could diverge once templates became editable data.
Ruling: mailable reads message.body_raw/subject directly, never
re-renders — the freeze is now structural on any QUEUE_CONNECTION.
Also from D0: there is NO scheduler-driven escalation send in the old
model (SweepEscalations only nags the tenant; letters go out on tenant
click) — confirming the silence model is a build, not a rewire.

Implementation landed: letter_templates + settings tables, Blade-free
LetterTemplateRenderer (whitelist, unknown-token passthrough),
evidence-freeze wiring at the named seam, 4 seeded templates, 5 seeded
settings, dual-write of legacy template_key, phase-8 design notes
marked superseded. Tests 377 → 396, +19, none weakened; retired
per-stage wording assertions enumerated in the report for future
reintroduction. Merged a775ad2 (--no-ff), pushed with tag.

## gafol deploy + the afternoon's detective work

Deploy hit two snags, both now recipe gotchas:

1. Faker missing — Plesk Git deploy doesn't run composer; dev deps had
   been stripped at some point. Fix: Toolkit composer install.
2. Sandbox 403 — DEV_*_EMAIL addresses needed adding AND confirming as
   sandbox authorized recipients.

Then a delivery-count mismatch (7 sent, 6 in Gmail, 1 unexplained)
turned into a proper reconciliation exercise. Root causes, all
evidenced: Gmail retains mail from prior lifecycle runs while the DB
truncates (match on case reference, not address); and Gmail is
progressively spam-blocking the sandbox domain with 550 5.7.1 hard
fails. Clean re-test after emptying Gmail: 10 accepted, 6 delivered,
4 failed-with-named-550 — DB, Mailgun log, and inbox reconcile
exactly. Phase 1 closed with a clean ledger.

The 4 silent failures justified snag #8 (delivery-status webhooks,
post-cutover): the platform must know when a frozen-as-evidence letter
didn't arrive.

## State at close

- main @ a775ad2 = Phase 1, deployed to gafol, verified.
- dotrent (production candidate) deliberately NOT updated — stays on
  pre-silence-model main until the full rewrite is proven on gafol,
  then takes it in one promotion (composer install at deploy).
- Local .env fixed (inbound domain de-bugged, sync queue, DEV_* vars).
- gafol .env gained DEV_* vars.
- Snag list: +#8 delivery-status blind spot.
- Recipe: v3 lines drafted (this doc).

## Next session pickup

1. Commit homework docs (snag #8, recipe v3, this write-up).
2. Phase 2a brief — shadow-mode clock: clock fields + turn detection +
   scheduler logic running alongside the old ladder, logging intended
   actions only, sending nothing. Settings become live reads
   ({{response_days}} source swap included). Old model fully intact;
   baseline stays green.
3. dotrent promotion deferred until the whole silence model is proven
   on gafol — one clean promotion, not per-phase drips.
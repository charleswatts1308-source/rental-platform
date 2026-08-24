# CLAUDE.md — Working Agreements

Standing rules. Project history and current-phase state live in `docs/`.

## Rewrites
- Phase briefs in `docs/cc-brief-*.md`. **Deliverable 0 is always a report — no code edits.** Hard stop until D0 is accepted.
- Implementation report stops at the end of every phase before merge.

## Git
- Branch per phase, tag `pre-<phase>` on main before branching, **zero commits to main** during a rewrite, `--no-ff` merge after green suite + acceptance met. **Never push without explicit user ask.**

## Authority
- `docs/llcs-silence-model-design.md` is authoritative and **wins over any brief**. Flag conflicts; don't silently follow the brief.

## Docs policy
- Auto-commit anything new or modified under `docs/` alongside the current change — no permission round-trip. Stable filenames for living docs; timestamps only on immutable write-ups.

## Mail (no exceptions)
- Production: Mailgun `mg.renters.rent`, both directions. (Preprod dotrent was **retired 1 Aug 2026** — the box is gone, not dormant. There is no preprod.)
- Staging: Mailgun sandbox, **outbound only** (sandbox cannot do inbound).
- Local: Mailpit only.
- Mailgun **webhook** work is tested on **live**. The sandbox cannot do inbound, so staging can never receive one — that limit is known and accepted, not worked around. Do not build a synthetic-signature test path to dodge it. Pattern: deploy behind a token, exercise with real sends, tear down and **verify gone**.
- `config/services.php` Mailgun keys carry **production defaults** (`mg.renters.rent`); env overrides per environment.

## Env allow-list
- `dev:*` artisan commands and `silence:sweep --pretend-today` are gated to `local/staging/preprod` via `app()->environment([...])`. Production refuses them.

## Evidential invariants
- Outbound landlord letters are **frozen** on `case_messages` at send time — the mailable reads `body_raw`/`subject` verbatim and never re-renders.
- Tenant nudges and tenant notifications are **mail-only**. They MUST NOT create `case_messages` rows — the escalation counter predicate (outbound system rows with non-null `stage_at_send`) depends on this.

## State machine + counter
- `RepairCase::TRANSITIONS` is the single source of truth; only `transitionTo()` may change status.
- Escalation counter is **derived** from `case_messages`, never stored, never reset (D3 ratchet).

## Time
- Time is an **injected parameter** through any sweep/clock/decision code (`CarbonInterface $now`). No `Carbon::setTestNow` in production code. No flag-branching on pretend / test modes — the same code path serves real, pretend, and test invocations.

## Migrations
- The test suite runs SQLite in-memory (`phpunit.xml`); dev/prod run MariaDB. SQLite cannot show MariaDB-specific schema behaviour — notably the implicit `ON UPDATE CURRENT_TIMESTAMP` trap (#18). Therefore: any migration that creates or alters a table gets a **manual MariaDB check before merge** — migrate against dev MariaDB, `SHOW CREATE TABLE` to confirm the built schema matches intent (plain `datetime` with no trailing `ON UPDATE`; indexes, FKs, defaults, and column types as intended), then rollback clean. **Green tests prove logic, not MariaDB schema.**

## Deployment ledger
- Every deploy to a long-lived environment (gafol, dotrent, prod) updates `docs/environment-state.md` as its **last step**: the commit/tag landed, the date, the migration set applied, and what was verified. A deploy isn't done until the ledger says it happened.
- The database's own `migrations` table is the **source of truth** for what ran where; the ledger is its human-readable mirror, **reconciled against `php artisan migrate:status`** at each deploy.
- Two long-lived environments at different commits is the condition that makes this non-optional.

## Tests
- **No weakened assertions, ever** — rewrites assert at least as strongly. D0 disposition list is the reference; deltas go in the implementation report.

# Landlord Contact Service — Implementation Plan

## Source of truth

The design document at `docs/landlord-contact-service-design.md` is the authoritative specification. This file is the implementation plan — what to build in what order, with what tests, and what counts as done.

**If implementation reveals a question the design document does not answer, stop and ask. Do not improvise design decisions.** Open questions get resolved in conversation with the human, not in the IDE.

## Pre-flight

Before starting Phase 1:

```bash
git checkout main
git pull
git checkout -b feature/landlord-contact-service
```

Confirm Pest is installed:

```bash
composer show pestphp/pest
```

If not present:

```bash
composer require pestphp/pest pestphp/pest-plugin-laravel --dev
php artisan pest:install
```

Run the existing test suite to confirm a green baseline before any new work:

```bash
php artisan test
```

If existing tests fail on `main`, stop and ask before proceeding.

Commit the design docs and any Pest setup as the first commit on the branch:

```
chore: add Landlord Contact Service design and implementation plan
```

## Standing rules (apply to every phase)

### Tests

- All tests are written in **Pest**. No PHPUnit-style test classes.
- Tests are written **alongside** implementation, not after. A migration without a test is incomplete. A model method without a test is incomplete. A controller action without a test is incomplete.
- Time-based logic (escalation sweep, hold expiry, dormancy) uses Laravel's `travel()` / `Carbon::setTestNow()` helpers — never real-clock-dependent tests.
- Database tests use the `RefreshDatabase` trait. Never test against a populated database.
- Run `php artisan test` before every commit. Do not commit failing tests.

### Naming

- The Eloquent model for the `cases` table is `RepairCase`, not `Case` (which is reserved in PHP). The model declares `protected $table = 'cases';`. File: `app/Models/RepairCase.php`.
- All foreign key columns stay as `case_id` regardless of the model rename.
- Relationship methods on related models (`CaseMessage`, `ReplyToken`, `CaseEvent`, `MessageAttachment`) are named `case()` and specify the FK explicitly: `$this->belongsTo(RepairCase::class, 'case_id')`. This keeps `$message->case` reading as domain language.
- Other model class names are unaffected: `LandlordContact`, `CaseMessage`, `ReplyToken`, `CaseEvent`, `MessageAttachment`. Only the bare word `Case` collides with PHP's reserved words.

### Migrations

- All FK columns declare `onDelete` behaviour explicitly per the design doc.
- All migrations are reversible (`down()` method drops what `up()` created).
- After each migration is added, verify both directions:
  ```bash
  php artisan migrate
  php artisan migrate:rollback
  php artisan migrate
  ```

### Models

- Models use snake_case table names matching the migrations.
- Every model has a corresponding factory in `database/factories/`.
- Casts are defined for every non-string column (timestamps, enums, json, integers, booleans).
- Relationships are declared explicitly. No magic.

### Code style

- Follow existing renters.rent code conventions. If conventions are unclear, ask.
- Bootstrap (already in the project) for any Blade views. Do not introduce Tailwind.
- Variables that hold money values use `int` cents, never float pounds.
- Enum values are PHP `BackedEnum`s where Laravel supports them, with the string value matching the database enum exactly.

### Commits

- Conventional Commits format: `feat(scope): description`, `test(scope): description`, `fix(scope): description`, `chore(scope): description`, `refactor(scope): description`.
- Scope is the phase or component (e.g. `feat(case-state-machine): add transitionTo method`).
- Commit at meaningful checkpoints within a phase, not just at phase end. A phase typically contains 5–15 commits.
- Each commit must leave the test suite green.

### Out-of-scope changes

- Do not modify `users` or `properties` tables, controllers, or models. The design treats them as untouchable. If a real need arises, ask before changing.
- Do not refactor existing renters.rent code unrelated to this feature, even if you spot opportunities. Note them in a `NOTES.md` instead.

## Phase plan

Each phase ends in a green test suite, a clean working tree, and an explicit acceptance check. The human runs the acceptance check before authorising the next phase.

### Phase 1 — Schema and model stubs

**Goal:** All six new tables migrated. Model classes exist with fillable, casts, relationships, and factories. No business logic yet.

**Deliverables:**
- Migrations for `landlord_contacts`, `cases`, `case_messages`, `reply_tokens`, `case_events`, `message_attachments` — column types, indexes, FKs exactly per the design doc
- Eloquent models for each, with relationships declared
- Factories for each model producing valid rows
- Pest tests:
  - Each migration runs up and down cleanly
  - Each factory produces a row that saves successfully
  - Each model's relationships return the expected related models

**Acceptance check:**
```bash
php artisan migrate:fresh --seed
php artisan test --filter=Phase1
```
Green tests, all six tables exist with correct schema (verify via MariaDB CLI or phpMyAdmin).

### Phase 2 — Case state machine

**Goal:** The `RepairCase` model owns all status transitions via a `transitionTo($newStatus, array $context = [])` method that validates, applies side effects, and writes events. Direct writes to `status` from outside the model are rejected.

**Deliverables:**
- `transitionTo` method on `RepairCase` model
- Transition table from the design doc encoded as a static array or class constant on the model
- A custom exception (`InvalidCaseTransitionException`) thrown for illegal transitions
- Event-writing logic invoked on every successful transition
- Pest tests:
  - One test per row of the design doc's state transition table — assert status change, assert side effects (events written, columns updated)
  - Tests asserting illegal transitions throw `InvalidCaseTransitionException`
  - Tests asserting `case_events` are written with correct `event_type`, `actor_label`, and `meta`

**Acceptance check:**
```bash
php artisan test --filter=CaseTransition
```
All 21 transitions covered, all illegal transitions tested.

### Phase 3 — Outbound mail composition and Mailgun send

**Prerequisite commits before Phase 3 proper begins:**

The `repair_categories` lookup table was added to the design after Phase 1 was completed, and Mailgun packages are not yet installed. Two prerequisite commits:

**Prerequisite 1: Mailgun packages and config**

```bash
composer require symfony/mailgun-mailer symfony/http-client
composer require mews/purifier
```

(`mews/purifier` is needed for Phase 4's HTML sanitisation but installing both transactional packages together is cleaner than splitting across phases.)

Add Mailgun configuration to `config/services.php` (Laravel's mail config likely already references `services.mailgun`; verify and add if missing). Add placeholder entries to `.env.example`:

```
MAIL_MAILER=log               # local dev stays on log driver
MAILGUN_DOMAIN=mg.renters.rent
MAILGUN_SECRET=                # populated in production .env, not committed
MAILGUN_ENDPOINT=api.eu.mailgun.net   # EU region for UK data residency
MAILGUN_WEBHOOK_SIGNING_KEY=   # populated in production .env, used in Phase 4
```

Do not change the working `.env` to switch from log to mailgun — that's a deployment-time change, not a code change.

**Prerequisite 2: repair_categories table and cases.category_key**

1. New migration: create `repair_categories` table per the design doc schema. Seeder populates the 11 starter categories.
2. Migration on `cases`: add `category_key varchar(50) NOT NULL` column with FK → `repair_categories.key` (RESTRICT). If the original Phase 1 migration of `cases` included a `category varchar(50)` column, this prerequisite either replaces it (drop column, add new) or modifies it (rename + add FK). Pick the approach that produces the cleaner migration history; the production database has no rows so either is safe.
3. Update factory and tests: `RepairCaseFactory` now supplies a valid `category_key` (FK-resolved against the seeded `repair_categories` table). Existing Phase 1 tests that exercised the `category` field need updating to match.

Run the full suite to confirm everything still passes after these changes, then proceed with Phase 3 proper.

**Phase 3 goal:** A tenant action triggers composition of a stage-appropriate letter, mints a reply token, dispatches via Mailgun, and records the outbound `case_message`.

**Deliverables:**
- A `SendCaseNotice` action class (single-purpose service) that orchestrates: token mint → message compose → Mailgun dispatch → event write
- A template registry — for v1, hardcoded array of stage_key → blade template path mapping
- Blade templates for stages 1, 2, 3, 4 (initial notice, follow-up, formal warning, pre-action). Plain factual content. Reference the property, the repair issue, and the relevant statute (Landlord and Tenant Act 1985 s.11 for repair obligations).
- Mailable class with subject, From, Reply-To set per the design doc
- Reply token generation (20-char base62) with collision-retry helper
- Pest tests:
  - Token generation produces unique, format-valid tokens
  - Mailable renders without error for each stage template
  - `SendCaseNotice` action writes the right rows in the right order
  - Mailgun client is mocked; assertion that the right call shape is made

**Acceptance check:**
```bash
php artisan test --filter=SendCaseNotice
```
Plus a manual `php artisan tinker` invocation that creates a test case and dispatches a notice, verifying the database state.

### Phase 4 — Inbound webhook

**Goal:** Mailgun's inbound webhook endpoint accepts landlord replies, verifies signatures, resolves tokens, sanitises HTML, applies sender-mismatch quarantine, and writes events.

**Deliverables:**
- Webhook route `POST /webhooks/mailgun/inbound` with no CSRF (whitelist in `bootstrap/app.php` or middleware config)
- `VerifyMailgunSignature` middleware that validates HMAC against `config('services.mailgun.signing_key')` and rejects with 406 if invalid
- `HandleInboundReply` action that: extracts token from recipient → resolves to case via `reply_tokens` (checking `expires_at`) → composes `case_message` row with `body_raw` and `body_sanitised` → applies sender-mismatch quarantine → writes events → triggers state transition on the case
- HTML Purifier package added (`composer require mews/purifier`) and configured
- Pest tests:
  - Valid signature accepted; invalid rejected with 406
  - Valid token routes to correct case
  - Unknown token logs and returns 200 (does not leak)
  - Expired token returns 200, no message stored
  - Superseded but in-window token routes correctly
  - Sender mismatch sets `quarantine_reason`, message stored but flagged
  - HTML body with `<script>` tags is stripped in `body_sanitised` but preserved in `body_raw`
  - Inbound from `awaiting_landlord` transitions case to `awaiting_tenant_review`
  - Inbound from `on_hold` transitions case to `awaiting_tenant_review`
  - Inbound to terminal state stores message but no transition

**Acceptance check:**
```bash
php artisan test --filter=Webhook
```
Plus manual end-to-end test using ngrok or Mailgun's test webhook tool to verify a real inbound message routes correctly.

### Phase 5 — Time-based jobs

**Goal:** Three scheduled tasks running daily: escalation eligibility sweep, hold expiry sweep, dormancy detection. Each idempotent — running twice in one day produces no extra effects.

**Deliverables:**
- Artisan command `cases:sweep-escalations` — finds cases with `next_stage_eligible_at <= NOW()` in `awaiting_landlord` or `on_hold` (where `hold_until <= NOW()`), transitions to `tenant_action_required`, sends notification email
- Artisan command `cases:sweep-holds` — finds cases in `on_hold` where `hold_until <= NOW()`, transitions to `tenant_action_required`
- Artisan command `cases:sweep-dormancy` — finds cases in `tenant_action_required` for 21+ days with no tenant activity, transitions to `dormant`. Reminder emails at 7 and 14 days (separately tracked, do not re-send).
- Schedule registration in `routes/console.php` (Laravel 11+) running each command daily at a sensible time
- Pest tests using `travel()`:
  - Escalation sweep picks up exactly the cases it should and transitions them
  - Idempotency: running the sweep twice produces one transition per case, not two
  - Hold expiry only affects cases past their `hold_until`
  - Dormancy reminders fire at correct day offsets
  - Dormant cases are not subject to escalation sweep

**Acceptance check:**
```bash
php artisan test --filter=Sweep
```
Plus `php artisan schedule:list` showing all three commands registered.

### Phase 6 — Tenant dashboard

**Goal:** Tenant can raise a new case, see a list of their cases, view a case in detail, and act on a `tenant_action_required` case.

**Deliverables:**
- Route `GET /cases` — list of authenticated tenant's cases
- Route `GET /cases/create` — form: property, repair category, severity, description, photos, landlord email, landlord name, landlord role
- Route `POST /cases` — creates the case in `open` state, immediately calls `SendCaseNotice` for stage 1, transitions to `awaiting_landlord`
- Route `GET /cases/{slug}` — case detail page: thread of messages, current state, available actions
- Routes for tenant actions: `POST /cases/{slug}/send-next`, `POST /cases/{slug}/hold`, `POST /cases/{slug}/resolve`, `POST /cases/{slug}/abandon`, `POST /cases/{slug}/re-engage`
- Bootstrap-styled Blade views for all of the above
- Authorisation: tenants can only see and act on their own cases. Use a Laravel Policy.
- Pest tests:
  - Tenant cannot see another tenant's case (403)
  - Form submission creates case and dispatches stage 1 notice
  - Action routes only accept transitions valid from current state
  - Photos upload and attach as `message_attachments`

**Acceptance check:**
Manual: log in as a test tenant, create a case end-to-end, verify the email lands at a Mailgun-routed test address, simulate a reply, verify it appears in the dashboard, take an action, verify the state transition.

### Phase 6.5 — Properties registration UI

**Goal:** Tenants can register the properties they rent through the dashboard. Closes the gap surfaced during Phase 6a where case creation requires a `properties` row but no UI exists to create one.

**Position in plan:** Lands between Phase 6a (case index + create) and Phase 6b (case detail + action routes). Phase 6b depends on this being complete because the case-creation flow exercised in 6b's tests needs a working properties UI to set up scenarios.

**Deliverables:**
- `PropertyController` with `index`, `create`, `store`, `edit`, `update` actions (delete deferred — properties with cases against them cannot be deleted per the FK RESTRICT, and properties without cases can be left as orphan records harmlessly).
- Routes inside the existing `auth, verified` middleware group: `GET /properties`, `GET /properties/create`, `POST /properties`, `GET /properties/{property}/edit`, `PATCH /properties/{property}`.
- Bootstrap-styled Blade views for list and form (`resources/views/properties/index.blade.php`, `resources/views/properties/create.blade.php`, `resources/views/properties/edit.blade.php`).
- `PropertyPolicy` enforcing that tenants can only see, edit, or attach cases to properties they registered (`registered_by_user_id` match).
- Pest tests:
  - Cross-tenant isolation on the index (tenant cannot see another tenant's properties)
  - Cross-tenant isolation on edit (403 on attempting to edit another tenant's property)
  - Validation: required fields (address line 1, postcode), postcode format check (UK postcode regex acceptable for v1)
  - Store creates the row with `registered_by_user_id` set to the authenticated user
  - Update modifies the existing row, does not create a new one
  - Attempting to update another tenant's property returns 403 without modifying anything

**Acceptance check:**
```bash
php artisan test --filter=Property
```
Plus manual verification: log in as a test tenant, register a property end-to-end through the new UI, then create a case against that property to confirm the case-creation flow now works without tinker intervention.

### Phase 7 — Tenant notification emails

**Goal:** Tenants receive notifications at the right moments: landlord reply received, escalation eligible, hold expired, dormancy reminders.

**Deliverables:**
- Mailables: `LandlordReplyReceived`, `EscalationEligible`, `HoldExpired`, `DormancyReminder`
- Each mailable's subject is neutral (does not include landlord content or specifics that would be sensitive in a notification preview)
- Each mailable contains a deep link to the case in the dashboard, no message content inline
- Notification dispatch wired into the existing transition / sweep code
- Pest tests:
  - Each mailable renders without error
  - Each mailable's subject is privacy-safe (no landlord email, no message content)
  - Notifications are dispatched on the correct transitions/events
  - `Mail::fake()` used to assert dispatch happens; no real mail sent in tests

**Acceptance check:**
Manual: trigger each notification scenario, verify the email arrives at the test tenant's address with correct content and a working link.

## Done criteria for the feature

- All seven phases complete
- `php artisan test` runs green with all new tests included
- Manual end-to-end smoke test: tenant raises a case, landlord replies, tenant escalates, landlord ignores, system surfaces dormancy, tenant abandons. All transitions logged in `case_events`.
- `docs/landlord-contact-service-design.md` and this file remain accurate. If implementation revealed any decision deltas, they are reflected in the design doc.
- Branch is rebased on current `main`, ready for merge.

## When implementation reveals a design question

If something in the design doc is ambiguous, contradictory, or impossible to implement as written:

1. Stop work on the affected component
2. Document the question in `NOTES.md` at repo root with: section of design doc, what's unclear, what options exist, what you recommend
3. Commit `NOTES.md`, push, and notify the human via the chat session that a design question is open
4. Resume work on unaffected components if any

Do not invent an answer. Do not pick the option that seems most reasonable. Design decisions are made in conversation with the human, not in the IDE.

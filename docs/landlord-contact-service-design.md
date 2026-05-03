# Landlord Contact Service — Consolidated Design

## Scope

Repair notice generator and correspondence engine for renters.rent. A tenant raises a repair issue against a property; the system sends a system-authored notice to the landlord (or agent), tracks the case, and supports tenant-controlled escalation through a sequence of letters. Documented correspondence record. Not mediation.

Built on existing Laravel/Breeze platform with MariaDB. Email handled by Mailgun (outbound) and Mailgun inbound webhook (replies). Tenant-facing dashboard for case visibility and decisions.

## Existing tables (unchanged)

- `users` — Breeze auth, holds tenants
- `properties` — owned/managed by users (tenants register the property they rent). Created as a Phase 1 prerequisite when the legacy `rentals` table was found to conflate property and tenancy concepts. The `rentals` table is left untouched alongside `properties` and is scheduled for retirement post-Phase 7.

## New tables

All PKs `bigint unsigned auto_increment`. No UUIDs anywhere. Timestamps are `timestamp` (MariaDB), nullable where indicated. `created_at` / `updated_at` standard Laravel pair on every table unless noted.

### `landlord_contacts`

The correspondent — landlord or letting agent. One row per email address.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `email` | varchar(255) NOT NULL | UNIQUE |
| `name` | varchar(255) NULL | |
| `role` | enum('landlord','agent') NOT NULL DEFAULT 'landlord' | letter templates branch on this |
| `organisation_name` | varchar(255) NULL | agency name; null for direct landlords |
| `invited_by_user_id` | bigint unsigned NOT NULL FK → users.id | first tenant who introduced them |
| `verified_at` | timestamp NULL | reserved for future identity confirmation |
| `created_at`, `updated_at` | | |

**Indexes:** `UNIQUE(email)`, `INDEX(invited_by_user_id)`

**Assumption:** email is globally unique across the platform. If the same person is both a direct landlord on one property and an agent on another, this won't model that cleanly — flag for revisit if it comes up. For v1 it's a reasonable simplification.

### `cases`

The correspondence case. One per (tenant, property, landlord_contact, repair issue).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `url_slug` | char(12) NOT NULL | UNIQUE; base62 random, used in URLs |
| `tenant_user_id` | bigint unsigned NOT NULL FK → users.id | |
| `property_id` | bigint unsigned NOT NULL FK → properties.id | |
| `landlord_contact_id` | bigint unsigned NOT NULL FK → landlord_contacts.id | |
| `category_key` | varchar(50) NOT NULL FK → repair_categories.key | repair category; see `repair_categories` table |
| `severity` | enum('routine','serious','emergency') NOT NULL DEFAULT 'routine' | drives Awaab's Law schedules later |
| `status` | enum(...) NOT NULL DEFAULT 'open' | see below |
| `current_stage` | tinyint unsigned NOT NULL DEFAULT 1 | which letter in the sequence |
| `next_stage_eligible_at` | timestamp NULL | when next escalation can be triggered |
| `hold_until` | timestamp NULL | tenant-set pause expiry |
| `opened_at` | timestamp NOT NULL | |
| `closed_at` | timestamp NULL | set when status moves to resolved/abandoned |
| `created_at`, `updated_at` | | |

**Status enum values:**
- `open` — case created, first notice not yet sent
- `awaiting_landlord` — letter out, waiting on response
- `awaiting_tenant_review` — landlord replied, tenant needs to read it
- `tenant_action_required` — escalation eligible, tenant must choose
- `on_hold` — tenant has paused; `hold_until` is set
- `resolved` — tenant marked repair complete
- `abandoned` — tenant chose to stop pursuing
- `dormant` — no tenant activity in 21+ days while in `tenant_action_required`

**Indexes:**
- `UNIQUE(url_slug)`
- `INDEX(tenant_user_id, status)` — tenant dashboard lookup
- `INDEX(status, next_stage_eligible_at)` — escalation sweep
- `INDEX(landlord_contact_id)` — Know Your Landlord aggregate views later
- `INDEX(property_id)`

### `case_messages`

Every letter in either direction. The audit-grade record of all correspondence.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `case_id` | bigint unsigned NOT NULL FK → cases.id | |
| `direction` | enum('outbound','inbound') NOT NULL | |
| `sender_role` | enum('system','tenant','landlord') NOT NULL | who is speaking; tenant means tenant-statement-bearing |
| `stage_at_send` | tinyint unsigned NULL | which case stage this letter belonged to |
| `template_key` | varchar(64) NULL | outbound only; identifies the template used |
| `subject` | varchar(500) NULL | |
| `body_raw` | text NOT NULL | exactly as composed (outbound) or received (inbound); never displayed for inbound |
| `body_sanitised` | text NULL | inbound only; HTML Purifier output, what dashboard renders |
| `tenant_statement` | text NULL | outbound only; tenant-authored insertion when present |
| `from_address_raw` | varchar(255) NULL | inbound only; preserved unmodified |
| `to_address_raw` | varchar(255) NULL | |
| `spf_pass` | tinyint(1) NULL | inbound; from Mailgun verification |
| `dkim_pass` | tinyint(1) NULL | inbound; from Mailgun verification |
| `mailgun_message_id` | varchar(255) NULL | for round-tripping to Mailgun API |
| `quarantine_reason` | varchar(100) NULL | inbound; non-null hides from main thread, surfaces in admin/warning |
| `sent_at` | timestamp NULL | outbound |
| `received_at` | timestamp NULL | inbound |
| `created_at`, `updated_at` | | |

**Indexes:**
- `INDEX(case_id, sent_at)`
- `INDEX(case_id, received_at)`
- `INDEX(mailgun_message_id)`

### `reply_tokens`

Inbound routing tokens. Hybrid model: one *active* token per case at any time, history retained for late-reply tolerance.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `case_id` | bigint unsigned NOT NULL FK → cases.id | |
| `token` | char(20) NOT NULL | UNIQUE; base62 random, ~119 bits entropy |
| `bound_email` | varchar(255) NOT NULL | landlord_contact.email at time of issue |
| `issued_at` | timestamp NOT NULL | |
| `expires_at` | timestamp NULL | hard expiry; null = no expiry |
| `superseded_at` | timestamp NULL | set when a newer token is minted for this case |
| `use_count` | int unsigned NOT NULL DEFAULT 0 | |
| `last_used_at` | timestamp NULL | |
| `created_at`, `updated_at` | | |

**Active token query:** `WHERE case_id = ? AND superseded_at IS NULL` returns at most one row. Inbound webhook looks up by `token` directly and verifies `expires_at` permits routing.

**Indexes:**
- `UNIQUE(token)`
- `INDEX(case_id, superseded_at)` — find active token for a case

**Assumption:** token rotates on every escalation. Old token gets `superseded_at = NOW()` and `expires_at = NOW() + 90 days` (configurable). A late landlord reply within the 90-day window still routes; outside it, the token is dead.

### `case_events`

Audit log. Every state-changing thing that happens to a case writes a row here. Read by future dashboards, court bundles, debugging, and any "what happened on day 23" query.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `case_id` | bigint unsigned NOT NULL FK → cases.id | |
| `event_type` | varchar(64) NOT NULL | controlled vocabulary |
| `actor_user_id` | bigint unsigned NULL FK → users.id | null for system-originated events |
| `actor_label` | varchar(32) NULL | 'system', 'tenant', 'landlord', 'admin' |
| `occurred_at` | timestamp NOT NULL | |
| `meta` | json NULL | structured payload specific to event type |
| `created_at` | timestamp | no `updated_at` — events are immutable |

**Initial event_type vocabulary:**
- `case_opened`, `case_resolved`, `case_abandoned`, `case_dormant`, `tenant_re_engaged`
- `notice_sent`, `inbound_received`, `inbound_quarantined`
- `escalation_eligible`, `escalation_confirmed_by_tenant`, `stage_advanced`
- `hold_set`, `hold_expired`
- `tenant_statement_added`
- `token_issued`, `token_superseded`, `token_expired`

**Indexes:**
- `INDEX(case_id, occurred_at)`
- `INDEX(event_type, occurred_at)`

### `message_attachments`

Files associated with a case_message — outbound (tenant's photos of the repair issue) or inbound (whatever the landlord sends).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `case_message_id` | bigint unsigned NOT NULL FK → case_messages.id | |
| `disk` | varchar(50) NOT NULL DEFAULT 'private' | Laravel filesystem disk name |
| `path` | varchar(500) NOT NULL | path within disk |
| `original_filename` | varchar(255) NULL | preserved for display |
| `mime_type` | varchar(100) NOT NULL | validated server-side, not trusted from upload |
| `size_bytes` | bigint unsigned NOT NULL | |
| `direction` | enum('outbound','inbound') NOT NULL | denormalised from parent for query convenience |
| `scan_status` | enum('pending','clean','infected','skipped') NOT NULL DEFAULT 'skipped' | hook for malware scanning; default skipped for v1 |
| `created_at`, `updated_at` | | |

**Indexes:** `INDEX(case_message_id)`

**Assumption for v1:** no malware scanning beyond Mailgun's default for inbound. Column reserved so it can be wired up later without a migration.

### `repair_categories`

Lookup table for case categorisation. Domain-extensible without migrations — categories evolve as the platform learns.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `key` | varchar(50) NOT NULL | UNIQUE; stable identifier (e.g. `damp_mould`); used in code and as FK target from `cases` |
| `label` | varchar(100) NOT NULL | human-readable display name (e.g. "Damp and mould") |
| `description` | varchar(500) NULL | guidance shown to tenants when selecting on the form |
| `sort_order` | int unsigned NOT NULL DEFAULT 0 | controls display order in dashboard form |
| `active` | tinyint(1) NOT NULL DEFAULT 1 | inactive categories stay resolvable for historical cases but don't appear in form |
| `requires_description` | tinyint(1) NOT NULL DEFAULT 0 | true for `other`; tenant must supply free text |
| `created_at`, `updated_at` | | |

**Indexes:** `UNIQUE(key)`, `INDEX(active, sort_order)`

**Initial seed (11 categories):**

| key | label | requires_description |
|---|---|---|
| `damp_mould` | Damp and mould | 0 |
| `heating` | Heating and hot water | 0 |
| `electrical` | Electrical | 0 |
| `plumbing` | Plumbing and drainage | 0 |
| `structural` | Structural (walls, ceilings, floors, roof) | 0 |
| `windows_doors` | Windows and doors | 0 |
| `pest_infestation` | Pest infestation | 0 |
| `appliances` | Landlord-supplied appliances | 0 |
| `safety` | Safety (alarms, gas, security) | 0 |
| `external` | External (gutters, drainage, garden) | 0 |
| `other` | Other | 1 |

`sort_order` for the seed: assign 10, 20, 30, ... in the order above (`damp_mould` first), with `other` last at 110. Gaps of 10 leave room for future insertions without renumbering.

**FK shape on `cases`:** `cases.category_key varchar(50)` references `repair_categories.key`. Foreign key on `key` rather than `id` keeps `case` rows human-readable in raw SQL without forcing a join. `onDelete RESTRICT` — categories with cases against them cannot be deleted; deactivate via `active = 0` instead.

## Process flows

### Outbound: notice send

1. Tenant opens new case from dashboard. Selects property, repair category, severity, dates issue first occurred. Uploads photos.
2. System resolves landlord_contact:
   - If tenant supplies an email matching an existing `landlord_contacts.email`, link to that row.
   - If new, create `landlord_contacts` row with `invited_by_user_id = tenant`, `role` from form input.
3. System creates `cases` row: `status = 'open'`, `current_stage = 1`, generates `url_slug`. The `created` boot hook on `RepairCase` writes a `case_opened` event automatically.
4. System mints a `reply_tokens` row for the case: random 20-char base62, `bound_email = landlord_contact.email`.
5. System composes outbound `case_messages` row from template `template_key = 'stage_1_initial_notice'`. Body assembled from template + property details + repair description + tenant_statement (if any).
6. Mail dispatched via Mailgun:
   - From: `"renters.rent cases" <cases@mg.renters.rent>` (or per tenant identity convention — see security/identity section)
   - Reply-To: `{token}@inbox.renters.rent`
   - To: `landlord_contact.email`
   - Attachments: outbound `message_attachments` (photos)
7. Mailgun returns message_id; stored on `case_messages.mailgun_message_id`.
8. `case_messages.sent_at` set, `case_events` rows written: `notice_sent`, `token_issued`.
9. Case status moves to `awaiting_landlord` via `transitionTo`. `next_stage_eligible_at` set per stage 1 schedule.

### Inbound: landlord reply

1. Landlord replies to `{token}@inbox.renters.rent`.
2. Mailgun's inbound route forwards to platform webhook endpoint. **First check:** verify Mailgun's HMAC signature on the webhook payload. Reject if invalid.
3. Extract token from recipient address. Look up `reply_tokens` by token.
4. Validate token: must exist, `expires_at` must be null or in future. If invalid, log and 200 OK (don't leak token validity to attackers via timing or bounce).
5. Resolve `case_id` via the token's `case_id`.
6. Build `case_messages` row:
   - `direction = 'inbound'`, `sender_role = 'landlord'`
   - `body_raw` = HTML body as received
   - `body_sanitised` = HTML Purifier output (`mews/purifier` package)
   - `from_address_raw` = From header as received
   - `spf_pass`, `dkim_pass` from Mailgun-supplied verification fields
7. **Quarantine check:** if `from_address_raw` does not match `landlord_contact.email` for the case (allowing for case-insensitivity and `+suffix` variants), set `quarantine_reason = 'unexpected_from_address'`. Message is stored but hidden from main thread; surfaced to tenant with a warning banner.
8. Process attachments into `message_attachments` rows.
9. Increment `reply_tokens.use_count`, set `last_used_at`.
10. Write `case_events`: `inbound_received` (or `inbound_quarantined`).
11. Update case status to `awaiting_tenant_review` via `transitionTo`.
12. Send notification email to tenant: subject "The landlord has responded to your case", deep-link to case in dashboard, no message content in the notification body.

### Escalation: stage advance

Daily scheduled job (Laravel scheduler) sweeps for cases ready for escalation:

```sql
SELECT id FROM cases
WHERE status IN ('awaiting_landlord', 'on_hold')
  AND (hold_until IS NULL OR hold_until <= NOW())
  AND next_stage_eligible_at <= NOW()
  AND closed_at IS NULL
```

For each:

1. Move status to `tenant_action_required` via `transitionTo`.
2. Write `case_events.escalation_eligible`.
3. Send notification email to tenant: "Your case is ready for the next step."

When tenant logs in and views the case, dashboard presents the four-option panel:
- **Send next letter** — proceeds with prepared stage N+1 letter
- **Hold** — tenant picks a date; `hold_until` set; status returns to `on_hold`
- **Mark resolved** — status → `resolved`, `closed_at` set
- **Mark abandoned** — status → `abandoned`, `closed_at` set; reason captured in `case_events.meta`

If tenant chooses **send**:

1. Mint new `reply_tokens` row for the case. Mark previous active token `superseded_at = NOW()`, `expires_at = NOW() + 90 days`.
2. Compose stage N+1 letter from appropriate template. Acknowledges any inbound landlord messages received since last outbound.
3. Send via Mailgun (same outbound flow as initial notice).
4. Increment `cases.current_stage`, set new `next_stage_eligible_at` per stage schedule.
5. Status → `awaiting_landlord`.
6. Write `case_events`: `escalation_confirmed_by_tenant`, `stage_advanced`, `notice_sent`, `token_issued`, `token_superseded`.

### Tenant disengagement: dormancy

If a case sits in `tenant_action_required` with no tenant activity:
- Day 7: reminder email
- Day 14: second reminder email
- Day 21: status → `dormant`, no further reminders, surfaces in admin view only

Dormant cases never escalate. Tenant can re-engage at any time which moves status back to `tenant_action_required`.

## Stage schedule (v1 working values)

| Stage | Letter | Days after previous |
|---|---|---|
| 1 | Initial repair notice | 0 |
| 2 | Follow-up reminder | 14 |
| 3 | Formal warning | 14 |
| 4 | Pre-action letter | 21 |

**Open item:** these defaults need review against current Pre-Action Protocol for Housing Conditions guidance and against Awaab's Law statutory timeframes (14 days investigation, 7 days repair start for serious hazards, 24 hours for emergencies). Severity = `serious` or `emergency` should compress the schedule. Assumption: schedule lives in code/config for v1 rather than a `case_message_templates` table; promote to a table when there's enough variation to justify it.

## Case status state machine

The `cases.status` enum drives the workflow. Every transition listed here is permitted; any transition not listed is illegal and must be rejected at the model layer. Implementation: a `transitionTo($newStatus, $context)` method on the `RepairCase` Eloquent model that validates the move against this table, applies side effects, and writes the appropriate `case_events` row. Direct writes to `status` outside this method are a bug.

**Model class naming:** The Eloquent model is named `RepairCase` because `Case` is a reserved keyword in PHP. The table remains `cases` (set via `protected $table = 'cases';` on the model). Foreign key columns stay as `case_id` everywhere. Relationship methods on related models are named `case()` and pass the FK explicitly — e.g. `$this->belongsTo(RepairCase::class, 'case_id')` — so calling code reads as `$message->case->status` in the domain language.

| From | To | Trigger | Initiator | Side effects |
|---|---|---|---|---|
| (start) | `open` | case form submitted | tenant | row created, `url_slug` minted, `opened_at` set; `case_opened` event written via `created` boot hook |
| `open` | `awaiting_landlord` | first notice send action | tenant (via dashboard) | reply_token minted; outbound case_message; Mailgun send; `next_stage_eligible_at` set per stage 1 schedule; events: `notice_sent`, `token_issued` |
| `awaiting_landlord` | `awaiting_tenant_review` | inbound webhook routes to case | system | case_message stored; events: `inbound_received` (or `inbound_quarantined` if from-mismatch) |
| `awaiting_landlord` | `tenant_action_required` | `next_stage_eligible_at <= NOW()`, daily sweep | system | tenant notification email; event: `escalation_eligible` |
| `awaiting_landlord` | `resolved` | tenant marks resolved | tenant | `closed_at` set; event: `case_resolved` |
| `awaiting_landlord` | `abandoned` | tenant marks abandoned | tenant | `closed_at` set; reason in event meta; event: `case_abandoned` |
| `awaiting_tenant_review` | `tenant_action_required` | tenant chooses action panel | tenant | event: `escalation_eligible` |
| `awaiting_tenant_review` | `on_hold` | tenant pauses | tenant | `hold_until` set; event: `hold_set` |
| `awaiting_tenant_review` | `resolved` | tenant marks resolved | tenant | `closed_at` set; event: `case_resolved` |
| `awaiting_tenant_review` | `abandoned` | tenant marks abandoned | tenant | `closed_at` set; reason in event meta; event: `case_abandoned` |
| `tenant_action_required` | `awaiting_landlord` | tenant sends next letter | tenant | new reply_token minted; old token `superseded_at` set, `expires_at` = now + 90 days; outbound case_message; Mailgun send; `current_stage++`; `next_stage_eligible_at` advanced per stage schedule; events: `escalation_confirmed_by_tenant`, `stage_advanced`, `notice_sent`, `token_issued`, `token_superseded` |
| `tenant_action_required` | `on_hold` | tenant pauses | tenant | `hold_until` set; event: `hold_set` |
| `tenant_action_required` | `resolved` | tenant marks resolved | tenant | `closed_at` set; event: `case_resolved` |
| `tenant_action_required` | `abandoned` | tenant marks abandoned | tenant | `closed_at` set; reason in event meta; event: `case_abandoned` |
| `tenant_action_required` | `dormant` | 21 days no tenant activity in this state, daily sweep | system | event: `case_dormant` |
| `on_hold` | `tenant_action_required` | `hold_until <= NOW()`, daily sweep | system | tenant notification email; event: `hold_expired` |
| `on_hold` | `awaiting_tenant_review` | inbound webhook routes to case | system | case_message stored; event: `inbound_received`. Hold is *not* cleared — `hold_until` persists, but landlord activity supersedes it for tenant attention |
| `on_hold` | `resolved` | tenant marks resolved | tenant | `closed_at` set; event: `case_resolved` |
| `on_hold` | `abandoned` | tenant marks abandoned | tenant | `closed_at` set; reason in event meta; event: `case_abandoned` |
| `dormant` | `tenant_action_required` | tenant views case | tenant | event: `tenant_re_engaged` |
| `dormant` | `abandoned` | admin closes case | admin | `closed_at` set; admin reason in event meta; event: `case_abandoned` (`actor_label = 'admin'`) |

**Rules out of band:**

- `resolved` and `abandoned` are terminal. No transitions out. If a tenant wants to revisit a closed case, they open a new one — closure is the boundary that makes the audit trail legally clean.
- The escalation timer (`next_stage_eligible_at`) only runs in `awaiting_landlord` and `on_hold`. It is paused in `awaiting_tenant_review`, `tenant_action_required`, `dormant`, and the terminals.
- `case_opened` is written automatically when a case row is created (via the model's `created` boot hook), with `actor_label='tenant'` and `actor_user_id=tenant_user_id`. The transition table starts at `(start) → open` for completeness; the implementation realises this as a creation hook rather than a `transitionTo` call.
- `hold_until` is not cleared on transitions out of `on_hold`. It persists as a historical record of the most recent hold for audit purposes.
- Direct writes to `cases.status` outside `transitionTo` are rejected by an `updating` boot hook that throws `InvalidCaseTransitionException::directWrite`. The hook catches Eloquent property assignment and `update()` calls. **Limitation:** raw query-builder mass updates (`DB::table('cases')->update(...)`) bypass Eloquent events and are not protected. Code in this project must never use raw query-builder updates against `cases.status`; use `transitionTo` exclusively.
- Multi-event transitions (e.g. `open → awaiting_landlord` writes `notice_sent`, with `token_issued` to follow when the token-minting side effect is wired) write the canonical state-change event in the state machine layer. Peripheral events for side effects (`token_issued`, `token_superseded`) are written by the code that performs those side effects — typically the action class that orchestrates the transition.
- Inbound mail can arrive in any non-terminal state. Behaviour:
  - `awaiting_landlord` → moves to `awaiting_tenant_review` (drawn).
  - `on_hold` → moves to `awaiting_tenant_review` (drawn).
  - `awaiting_tenant_review` → stays in `awaiting_tenant_review`, additional message added to thread.
  - `tenant_action_required` → stays in `tenant_action_required`, additional message added; tenant sees both the escalation prompt and the new reply.
  - `dormant` → moves to `awaiting_tenant_review`. Inbound from the landlord wakes a dormant case (it's a signal that the tenant's attention is now warranted).
- Inbound mail to terminal states (`resolved`, `abandoned`) is stored on the case_messages table for the audit record but does not change status. Tenant sees a notification but the case stays closed.

## Security model

**Token entropy:** 20 base62 characters = ~119 bits. Use `Str::random(20)` in Laravel and validate `[A-Za-z0-9]{20}` on inbound. Never sequential, never derived from case_id.

**Token leakage:** mitigated by 90-day post-supersession expiry on every token. A token from 18 months ago in a court bundle no longer routes. Active token revocation possible by setting `superseded_at` on the row.

**Inbound HMAC verification:** every webhook hit verifies Mailgun's signature against the configured signing key. No exceptions, including in development (use a separate dev signing key).

**HTML sanitisation:** every inbound `body_raw` runs through HTML Purifier (`mews/purifier`) before storage in `body_sanitised`. Dashboard renders only `body_sanitised`. `body_raw` accessible only via admin tooling, never via tenant routes.

**SPF/DKIM:** Mailgun supplies verification flags on inbound webhook payload. Stored on `case_messages` for audit. Not currently used to block — but messages where both fail get a flag in the dashboard so the tenant can weigh authenticity.

**Sender mismatch quarantine:** when inbound `from_address_raw` doesn't match the case's expected landlord_contact email, message is stored with `quarantine_reason` set. Surfaced to tenant with explanatory warning. They can choose to accept it into the thread (and the system updates `landlord_contact.email` for future correspondence) or dismiss it.

**Tenant identity in From header:** the From address on outbound letters identifies the tenant by first name only, with no surname or real email exposed. Format: `"{tenant first name} via renters.rent" <cases@mg.renters.rent>`. The landlord knows who they're dealing with; the platform shields the tenant's real contact details. The Reply-To is the per-case token address; landlords cannot reply to the tenant directly.

**Audit trail:** `case_events` is the legal-grade record. Every state change writes a row. Treat as append-only — no updates, no deletes.

**Items deferred:**
- Per-token rate limits
- Per-IP webhook rate limits
- Active malware scanning of attachments
- Quarantine review workflow for admin

These are real concerns but worth implementing once attack patterns are observed rather than guessed.

## Implementation notes for Claude Code

- All migrations should use `bigint unsigned` for PKs and FKs. Laravel's `$table->id()` and `$table->foreignId()` produce this by default.
- Foreign key onDelete behaviours, all explicit at the migration site (`->restrictOnDelete()`, `->cascadeOnDelete()`, `->nullOnDelete()`):

| FK | onDelete | Rationale |
|---|---|---|
| `landlord_contacts.invited_by_user_id` | RESTRICT | Contact is shared across tenants; deleting the inviter must not cascade |
| `cases.tenant_user_id` | RESTRICT | Active cases must be closed before tenant deletion (admin workflow, not cascade) |
| `cases.property_id` | RESTRICT | Properties with cases are part of the legal record |
| `cases.landlord_contact_id` | RESTRICT | Contact deletion must not orphan or destroy cases |
| `cases.category_key` | RESTRICT | Categories with cases cannot be deleted; use `active = 0` instead |
| `case_messages.case_id` | CASCADE | Messages are owned by their case |
| `reply_tokens.case_id` | CASCADE | Tokens are owned by their case |
| `case_events.case_id` | CASCADE | Events are owned by their case |
| `case_events.actor_user_id` | SET NULL | Immutable audit trail survives user deletion; actor_label still records role |
| `message_attachments.case_message_id` | CASCADE | Attachments are owned by their message |

- `url_slug` generation: helper that calls `Str::random(12)` and retries on collision (vanishingly unlikely but cheap to handle).
- Token generation: `Str::random(20)`. Same retry-on-collision pattern.
- HTML Purifier: `composer require mews/purifier`, then sanitise via `Purifier::clean($html)`. Default config is reasonable; can tighten later.
- Mailgun: outbound via `symfony/mailgun-mailer` and `symfony/http-client` (Laravel's mail facade routes through Symfony's transport). Inbound webhook needs a new route (`POST /webhooks/mailgun/inbound`) and a `Route::post(...)->withoutMiddleware(VerifyCsrfToken::class)->middleware('verify.mailgun.signature')` setup. Mailgun region: EU (`api.eu.mailgun.net`) for UK data residency.
- Mail dispatch: outbound notices are queued via `Mail::to(...)->queue($mailable)` rather than sent synchronously. SMTP transient failures must not cause case-state rollback. Local dev runs the `sync` queue driver so behaviour is effectively synchronous; production uses `database` or `redis` with retry. Consequence: `mailgun_message_id` capture is deferred — the value is only available via Symfony's `MessageSent` event, which is wired in a later phase when message-id round-tripping is needed.
- Email Blade templates use inline styles, not Bootstrap. The "Bootstrap for any Blade views" standing rule applies to dashboard views only — email clients (Outlook, Gmail, Apple Mail) do not render external stylesheets reliably, so email HTML must use inline styles per the email-development convention.
- Email subject line format for outbound notices: `"{stage label} — {address line 1}, {postcode}"`. Stage label examples: "Initial Repair Notice", "Follow-Up Reminder", "Formal Warning", "Pre-Action Letter". Format keeps the tenant's case identifiable in their inbox and the landlord's case identifiable in theirs.
- Job for escalation sweep: Laravel scheduled command running daily at e.g. 06:00 UTC. Idempotent — running it twice in one day must produce no extra escalations.
- Notification emails to tenant: separate Mailable classes per event type. Keep notification subjects neutral; never include landlord content in the subject or preview text (privacy).

## Open items to resolve before build

1. Stage schedule day offsets — confirm against current PAP and Awaab's Law guidance. Likely needs solicitor / housing law review at some point.
2. Inbound subdomain — `inbox.renters.rent` confirmed. Mailgun account and DNS configuration deferred until closer to go-live (post-Phase 6).
3. Outbound From address — `cases@mg.renters.rent` (sending subdomain), with display name `"{tenant first name} via renters.rent"`. Mailgun setup wizard handles SPF/DKIM/DMARC alignment when the account is created.
4. Hold duration — tenant picks any future date, or constrained to a set of options (7, 14, 30 days)?
5. Inbound attachment processing — design's inbound flow step 8 ("Process attachments into message_attachments rows") was not implemented in Phase 4 (not in the implementation plan's deliverables). Decision needed on which phase implements this and whether attachment scanning policy needs work first.

## Deferred decisions

Decisions taken during implementation that have not yet been folded into the main body of this document. Each entry references the phase that produced it. When the related main-body section is next revised, deferred decisions for that area should be merged in and removed from this list.

### Phase 4

- **`event_type_override` context key on `transitionTo`.** Allows the same (from, to) transition pair to write different events based on context — specifically `inbound_received` vs `inbound_quarantined` for the same `awaiting_landlord → awaiting_tenant_review` transition. Defaults still come from the `TRANSITIONS` map; the override is opt-in.
- **`dormant → awaiting_tenant_review` added to the formal transition map.** The design doc's transition table omitted this row, but the "rules out of band" section explicitly required the inbound-wakes-dormant behaviour. Adding to the formal map keeps the state machine the single source of truth. **Design doc body should be updated to include this row in the transition table at the next revision.**
- **15-minute replay window on Mailgun signature verification.** Defensive default not specified by design but consistent with Mailgun's documented best practice. Webhook payloads with timestamps older than 15 minutes are rejected as potential replays.

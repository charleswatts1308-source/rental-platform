# Phase 8 — Configurable letter sequence and templates (parked design notes)

**Status:** Not yet scheduled. These are design decisions reached during conversation and parked for reference. To be turned into a build-ready design doc when Phase 8 is the next phase on the agenda.

## Why this phase exists

The current LLCS build hardcodes the letter sequence: 4 stages, fixed day offsets (14/14/21), Blade-template letter content. Real-world platform operation will need:

1. Editable letter content (typo fixes, legal-reference updates, tone improvements)
2. Editable stage structure (adding/removing stages, adjusting timing)
3. The ability to redesign the sequence — for example, splitting the current Stage 1 into a "please confirm receipt" letter followed by a "repair notice" letter, leveraging the four-cases framing (reply / invalid reply / no reply / bounce) for stronger legal positioning

## Schema

Three tables:

- `letter_stages` — master config edited by admins. Columns: id, stage_number, key, label, days_after_previous, active, notes, timestamps.
- `case_stages` — per-case snapshot populated at case creation. Cases follow their snapshot for their lifetime, regardless of subsequent admin edits to `letter_stages`. Columns: id, case_id, stage_number, key, label, days_after_previous.
- `letter_templates` — content, edited by admins, no per-case snapshot. Columns: id, stage_key (FK to letter_stages.key), subject_template, body_template, active, timestamps.

## Propagation rules

Two distinct edit types with different propagation behaviour:

**Content edits (letter_templates):** admin decides per save whether to apply immediately to in-flight cases or only to new cases. Default is "new cases only." Errors of slow propagation are recoverable; errors of unintended cascade are not. UI presents two save buttons: "Save (new cases only)" as default, "Save and apply to in-flight cases" as secondary with confirmation step.

**Structure edits (letter_stages):** always new cases only. In-flight cases run on their `case_stages` snapshot. Snapshot pattern protects against admin mistakes — a misconfigured stage doesn't reschedule existing cases.

**Cases not yet sent:** the propagation question only matters for cases that have already received at least one letter. Cases in `open` state when admin edits the template render with the latest content when their first send fires (consistent with content-immediate rule).

## Placeholder substitution

Templates use `{placeholder}` syntax interpolated at render time. Available placeholders:

- `{tenant_first_name}`, `{tenant_full_name}`
- `{property_address}`, `{property_postcode}`
- `{landlord_name}`, `{landlord_email}`, `{landlord_role}`
- `{repair_category}`, `{repair_description}`, `{severity}`
- `{case_opened_date}`, `{previous_letter_date}`
- `{stage_label}`, `{stage_number}`
- `{reply_token_address}`

Simple `Str::replace` substitution; no full templating engine. Admin-editable content, not admin-editable logic.

## Validation on template save

- Required placeholders must be present (per-stage list — minimum `{property_address}` and `{landlord_name}`)
- Unknown placeholders flagged (catches typos that would render literally)
- HTML body sanitised on save (admin can't accidentally inject script via templates)
- Render against sample data and show preview before save

## Admin gate

For v1, hardcoded user IDs in `config/admin.php`. Middleware checks `auth()->id()` against array, 403 otherwise. When platform grows beyond solo admin, replace with proper roles system.

## UI surface

Bootstrap views per project convention.

- Letter Stages page: list of stages in order, drag to reorder, Edit and Add buttons
- Stage editor: label, days_after_previous, notes, save with new-cases-only confirmation
- Letter Template editor: subject and body fields, placeholder reference panel, Preview button rendering with sample data, two save buttons (default new-cases-only, secondary apply-to-in-flight with confirmation)

## Migration from current hardcoded state

Seeder reads the existing 4 Blade letter templates, extracts subject and body, populates the new tables. Manual translation needed because Blade syntax (`{{ $caseMessage->case->property->address_line1 }}`) becomes placeholder syntax (`{property_address}`). Only 4 templates so manual translation is fine.

After migration, Blade files become unused and can be deleted.

## Anticipated sub-commits

1. Schema migrations + seeder importing current templates
2. Admin gate middleware + config
3. Letter stages CRUD (controller, views, tests)
4. Letter templates editor with preview (controller, views, tests)

Followed by a separate non-Phase-8 commit redesigning the actual sequence (admin clicks through new UI to set up confirmation-first stages).

## Out of scope for Phase 8

- Multiple templates per stage (e.g. severity-driven variants) — defer
- Severity-driven schedule scaling (`days_serious`, `days_emergency` columns) — defer
- Versioned template history beyond `case_messages.body_raw` — Git history of `letter_templates` rows via audit log if needed
- Roles system for admins — hardcoded user IDs sufficient for v1
- Retroactive stage redesign (applying new structure to in-flight cases) — manual admin operation only when needed, not built into Phase 8

## Related decisions captured elsewhere

- Bounce handling (Mailgun events webhook) is a separate future phase, parked in the design doc's open items
- Land Registry-anchored landlord identity for de-duplication is the long-term architectural answer for the duplicate-contact problem; out of scope for Phase 8

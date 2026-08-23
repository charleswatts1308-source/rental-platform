# CC REPORT — Delivery events (snag #25), Deliverable 0

**Status:** **ACCEPTED by Charlie 2026-08-09**, including both rulings
sought at the foot of this report:
- **D0.2 — a second middleware**, not a widening of the existing one.
- **D0.8 step 3 — a real prod send BEFORE the controller is written.**

Build proceeds in the D0.8 order. Steps 1–2 need nothing from Charlie;
step 3 is a hard gate requiring prod.
**Branch:** `feature/delivery-events` off `main` (`1a46766`); tag
`pre-delivery-events` on main. No commits to main.
**Inputs read:** CLAUDE.md; `llcs-silence-model-design.md` (authoritative);
`delivery-failure-design-question.md` incl. the nine rulings R1–R9 taken
2026-08-09, which serve as the brief; snags #25, #47.

All file:line citations verified against the working tree this session.

---

## 0. Authority flags (must rule before build)

**0.1 — the design doc is silent on delivery failure.**
`llcs-silence-model-design.md` assumes throughout that a sent letter
arrives. It contains no D-section covering non-delivery, bounce,
complaint, or a terminal state reached by transport failure. CLAUDE.md
makes that doc authoritative over any brief — so building R1–R9 means
the authoritative record currently says nothing about a load-bearing
behaviour we are adding.

**Required:** the design doc gains a section covering delivery failure
(proposed: **D17**), recording R1–R9, before or at build start. I will
not build past the doc in silence. This is a doc edit at build start,
not now.

**0.2 — `contact_failed` is a state-machine addition.**
`RepairCase::TRANSITIONS` (`RepairCase.php:230`) is the single source of
truth and only `transitionTo()` may change status (CLAUDE.md). Adding a
terminal status requires explicit new entries, and a ruling on which
statuses may reach it. From R3/R7 the answer is at least `Open` and
`AwaitingLandlord` (bounce/complaint on letter 1) and, for complaints,
any status from which a letter can have been sent. **Needs enumerating
against TRANSITIONS at build start, not inferred.**

---

## D0.1 — What exists today (verified)

- **One** Mailgun route: `routes/web.php:188-190`,
  `POST /webhooks/mailgun/inbound` → `MailgunInboundController`, wrapped
  in `verify.mailgun.signature`.
- **One** webhook controller: `app/Http/Controllers/Webhooks/` contains
  only `MailgunInboundController`.
- Middleware alias registered at `bootstrap/app.php:24`.
- No delivery-event consumption of any kind. Confirms snag #25's
  addendum: nothing would receive `delivered` / `failed` / `complained`
  even if they are subscribed in the Mailgun dashboard.

**`case_messages` already carries `mailgun_message_id`**
(`create_case_messages_table.php:28`), **indexed** (`:36`). It is
populated **inbound only** (`HandleInboundReply.php:114`); outbound rows
leave it null. Worth knowing, though R-series correlation does not depend
on it (see D0.3).

---

## D0.2 — ⚠ THE SIGNATURE MIDDLEWARE IS NOT REUSABLE AS-IS

**This contradicts the fix line in snag #25**, which states the new
controller can "reuse `verify.mailgun.signature`". On the evidence that
is wrong, and the failure mode is bad.

`VerifyMailgunSignature` (`app/Http/Middleware/VerifyMailgunSignature.php:37-43`)
reads three **flat, top-level** fields:

```php
$timestamp = (string) $request->input('timestamp', '');
$token     = (string) $request->input('token', '');
$signature = (string) $request->input('signature', '');
```

That is the shape Mailgun's **inbound routing** posts. Mailgun's
**delivery-event** webhooks post JSON with the signature fields **nested
under a `signature` object**, alongside `event-data`. Against that
payload:

- `input('timestamp')` and `input('token')` resolve to `''`
- `input('signature')` resolves to an **array**, not a string

→ the middleware returns **406** at `:42`.

**And 406 is the worst possible failure here.** The class docblock
(`:18-19`) records that 406 is chosen deliberately because **Mailgun
stops retrying on 406**. So every delivery event would be permanently
discarded, silently, with no retry and no trace — while the app appeared
to have a working webhook. That is the same class of silent blind spot
#25 exists to remove.

**Caveat, stated honestly:** the delivery-event payload shape above is
from documentation knowledge, not from a payload I have observed, and my
knowledge has a cutoff. It must be confirmed against a real event before
building. But the safe reading is the reverse of #25's: **assume the
middleware is incompatible until a real payload proves otherwise**,
because assuming compatibility fails closed-and-silent.

**Proposed:** a second middleware (e.g. `VerifyMailgunEventSignature`)
sharing the HMAC logic but reading the nested shape, with its own tests
against a captured payload. Do **not** widen the existing one — it
guards the inbound path that already carries real landlord replies, and
loosening its field lookup to accept either shape would weaken a
security control on evidence-bearing traffic to save a file.

---

## D0.3 — Correlation (confirms #25's retraction)

`CaseNotice` is constructed with the `CaseMessage` itself
(`CaseNotice.php:36`), and its `envelope()` already passes `tags:`
(`CaseNotice.php:81`). Adding `metadata: ['case_message_id' => $this->message->id]`
beside it is a one-line change requiring no listener and no change to
send logic. This confirms the retraction recorded in snag #25 and needs
no further work at D0.

**Unverifiable locally:** that Symfony's Mailgun transport maps Envelope
metadata to `v:` custom variables and that Mailgun returns them as
`user-variables` on delivery events. Must be proven with one real send
before the webhook is built, because everything downstream depends on it.

---

## D0.4 — Status opt-out inventory (snag #47, now precise)

Snag #47 says "there are likely more" opt-out lists. **Verified: there
are not — in `app/` there are exactly two, and the rest are allow-lists.**
That materially shrinks #47's fix.

**Dangerous (deny-lists — a new status is opted IN by default):**

| site | effect of forgetting |
|---|---|
| `SilenceSweep.php:104` `whereNotIn('status', …)` | the case **is swept** |
| `SilenceClock.php:60` `NO_CLOCK_STATUSES` | the case **gets a silence clock** |

**Safe (allow-lists — a new status is inert by default):**

- `RepairCase::TRANSITIONS` (`:230`), gated by `array_key_exists` (`:292`)
  — no legal transitions in or out until added.
- `RepairCase::showsNextEscalation()` (`:146`) — requires
  `=== AwaitingLandlord`.
- `RepairCase::showsHoldUntil()` (`:175`) — requires `=== OnHold`.
- `RepairCasePolicy::hold()` (`:97`), `::resolve()` (`:105`) — `in_array`
  allow-lists.
- `SendCaseNotice::assertEntryStatusAllowed()` (`:247`) — allow-lists,
  throws otherwise.

**One correctness gap, not a hazard:** `RepairCase::isClosed()` (`:126`)
allow-lists only `Resolved` and `Abandoned`. A terminal `contact_failed`
would report **not closed**. It gates display, not transitions
(`:123-124`), so nothing dangerous follows — but it would be wrong and
must be included.

**Ruling stands:** #47's enumerating test is a prerequisite. Scope is now
known — two deny-lists plus `isClosed()`.

---

## D0.5 — Schema

Per R8, delivery outcome lives on `case_messages`, not on `cases`.
Proposed columns: delivery status, event timestamp, and the detail
Mailgun supplies (SMTP code, response string, MX host, attempt number).

Two constraints:

1. **CLAUDE.md #18 — manual MariaDB check before merge.** This alters a
   table, so the gate applies: migrate against dev MariaDB,
   `SHOW CREATE TABLE`, confirm plain `datetime` with no trailing
   `ON UPDATE`, then roll back clean. Use `dateTime()` rather than
   `timestamp()` for any new time column, per #18's own fix guidance.
2. `cases.status` is a **MariaDB `enum`** (`create_cases_table.php:25`),
   so adding `contact_failed` is a second `ALTER TABLE`, also under #18.

**Field names must be verified against a real payload before building.**
Snag #25 already flags that its own list is from documentation, not
observation.

---

## D0.6 — The mail-only invariant

R-series requires notifying the tenant on a bounce. CLAUDE.md is explicit:
tenant notifications **must not** create `case_messages` rows, because
outbound system rows with a non-null `stage_at_send` **are** the
escalation counter (`SilenceClock.php:143-150`, a plain `->count()`).

**The pattern to copy already exists.** `SilenceSweep.php:627, 675, 722`
send `AutoEscalationTenantNotice` via `Mail::to($case->tenant->email)->queue(...)`
with no message row written. The new notification follows that exactly.

This is the single thing in this build that could quietly break the
product, and it needs an explicit test asserting the counter is unchanged
across a bounce-and-notify cycle.

---

## D0.7 — Not verifiable locally

Recorded so they are not discovered late:

1. The delivery-event payload shape and signature nesting (D0.2).
2. That Envelope metadata survives to `user-variables` (D0.3).
3. Whether Mailgun converts an exhausted soft bounce into a
   `permanent_fail` (ruling R2's build check).
4. Whether a `List-Unsubscribe` header is being attached to case notices
   (R7's build check).
5. Mailgun behaviour when sending to a suppressed address.

All five need a real send and a real event against `mg.renters.rent`.
**Prod is the only environment that can produce them** — staging is a
sandbox domain, local is Mailpit. A deliberate send to a dead address is
the cheapest way to get (1), (2) and (5) at once.

---

## D0.8 — Proposed build order

1. **Snag #47 first** — the enumerating status test. Scope known from
   D0.4. Without it, step 4 can silently escalate unreachable cases.
2. Design doc gains **D17** recording R1–R9 (authority flag 0.1).
3. Envelope metadata line + one real prod send to capture a genuine
   event payload. **Everything after this depends on what that payload
   shows.**
4. Migration: `case_messages` delivery columns + `contact_failed` enum
   value. Manual MariaDB check (#18).
5. `VerifyMailgunEventSignature` + tests against the captured payload.
6. Delivery-event controller + route.
7. Case transitions, tenant notification (mail-only), case-page display.
8. Live proof: deliberate send to a dead address.

Steps 1–2 are safe to do now. **Step 3 is a gate** — I would not write
the controller before seeing a real payload, given D0.2.

---

## D0.9 — Test disposition (provisional)

**New:** status classification enumerator (#47); signature verification
against a captured event payload incl. the nested shape and the 406
paths; correlation of an event to the right `case_message`; bounce →
`contact_failed` transition; complaint → `contact_failed` from mid-ladder
statuses; **counter unchanged across bounce-and-notify** (D0.6);
`delivered` capture; soft-bounce recorded but no tenant action; copy-case
carrying property/category/severity/description/photos.

**Expected changes to existing tests:** any test asserting the exhaustive
set of statuses, and `isClosed()` coverage. No weakened assertions —
deltas will be listed in the implementation report per CLAUDE.md.

**Known blind spot to state plainly:** the suite cannot exercise Mailgun.
Signature verification can be tested against a captured fixture, but that
fixture is only as good as the payload it was captured from. Everything
in D0.7 is proven on prod or not at all.

---

## Hard stop

No code will be written until this report is accepted. The two decisions
I most want a ruling on:

- **D0.2** — do you accept a second middleware rather than widening the
  existing one? It costs a file and protects the inbound path that
  already carries real landlord replies.
- **D0.8 step 3** — do you accept that a real prod send comes *before*
  the controller is written, rather than building against documented
  payload shapes and hoping?

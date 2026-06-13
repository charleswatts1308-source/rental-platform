# CC REPORT — D15 Deliverable 0 (engagement-gated escalation)

**Status:** D0 — report only, ZERO code edits. Awaiting Charlie's ruling
before any build.
**Branch:** `d15-engagement-gating` off `main` (`bc743e9`); tag
`pre-d15` on main. No commits to main.
**Inputs read:** CLAUDE.md, `llcs-silence-model-design.md` (D1–D14,
authoritative), `cc-brief-d15.md`, and the D15 fact-find (this session).

All file:line citations verified against the working tree this session.

---

## 0. Authority flag (must rule before build) — D15 vs D7

`llcs-silence-model-design.md` is authoritative and **wins over the
brief** (CLAUDE.md). **D7** ([design doc lines 152-171](llcs-silence-model-design.md))
states escalation is **silence-only, no tenant-initiated escalation**,
and explicitly demolished `CaseController::sendNext` + its UI, with the
rationale "the system cannot judge reply quality, so it must not offer
the tenant a button whose meaning is 'I judged this reply inadequate'".

D15 reintroduces a tenant action that triggers an escalation send (the
authorise button for the engaged-quiet class). The brief acknowledges
this as "a deliberate, partial reversal of D7 … on legitimate new
grounds (the thank-you harm post-dated D7)". I agree the D15 button is
**not** D7's banned button — it does not mean "I judge this reply
inadequate"; it means "authorise the next notice the platform
prepared" — but **the authoritative doc currently says the opposite of
what we are about to build.**

**Required:** the design doc gains a **D15** section that supersedes/
qualifies D7 (with a back-pointer from D7), so the authoritative
record matches the code. I will not silently follow the brief past the
doc. This is a doc edit to make at build start, not now. Flagging per
the CLAUDE.md authority rule.

---

## D0.1 — the engagement flag

**Proposed column:** `cases.landlord_engaged` — boolean, NOT NULL,
default `false`. Set once, false→true, never reset.

**Migration shape** (consistent with existing `cases` add-column
migrations, e.g. `2026_06_07_080002_add_dormant_at_to_cases_table` and
`2026_06_06_151459_add_silence_clock_to_cases_table`):

```php
Schema::table('cases', function (Blueprint $table) {
    $table->boolean('landlord_engaged')->default(false)->after('ball_with');
});
```
Add `'landlord_engaged'` to `RepairCase::$fillable`
([RepairCase.php:41-58](app/Models/RepairCase.php#L41-L58)) and cast
`'boolean'` ([:60-73](app/Models/RepairCase.php#L60-L73)). No enum, no
state-machine touch.

**The single chokepoint — confirmed.** A real landlord inbound is
processed in exactly one place: **`HandleInboundReply::execute()`**
([HandleInboundReply.php:61-159](app/Actions/HandleInboundReply.php#L61-L159)).
`MailgunInboundController::__invoke` is a thin delegate and the only
route into it ([MailgunInboundController.php:22-27](app/Http/Controllers/Webhooks/MailgunInboundController.php#L22-L27)).
The flip belongs next to the existing clock-flip block, inside the same
`DB::transaction`, at [HandleInboundReply.php:116-125](app/Actions/HandleInboundReply.php#L116-L125)
(where `ball_with='tenant'` + clock restart already happen on every
inbound).

- **Idempotent:** `$case->landlord_engaged = true;` is idempotent by
  construction; a second inbound re-sets the same value. Persisted by
  the existing `transitionTo()`/`save()` in the same block — no extra
  write.
- **Never resets:** nothing in the codebase would set it back to false.
  Grep target at build: confirm no other writer. (Default-false is set
  only at row creation in `confirm()`,
  [CaseController.php:363-374](app/Http/Controllers/CaseController.php#L363-L374),
  which omits the column → DB default.)
- **Only writer of inbound landlord messages:** `HandleInboundReply` is
  also the sole writer of `case_messages` rows with
  `direction=Inbound, sender_role=Landlord`
  ([:97-110](app/Actions/HandleInboundReply.php#L97-L110)). `dev:reply`
  (DevReply.php) is the local-only simulator and routes through the
  same action — it inherits the flip for free. Confirm at build.

**Critical definition — what counts as "engaged" — ONE RULING NEEDED.**
The chokepoint already classifies inbound by sender identity:
`resolveQuarantineReason()` compares the From address to
`landlord_contact.email` (case-insensitive, `+suffix` tolerant) and
stamps `quarantine_reason='unexpected_from_address'` on a mismatch
([:95, 228-268](app/Actions/HandleInboundReply.php#L228-L268)).
Crucially, **today a quarantined inbound still flips the ball/clock** —
the comment at [:119-122](app/Actions/HandleInboundReply.php#L119-L122)
says quarantine is "irrelevant" to the silence model.

There is **no** auto-responder / out-of-office / bounce detection
anywhere. No parsing of `Auto-Submitted`, `Precedence: bulk`, etc.
Bounces do not even reach this action (they return to a different
address, not the 20-char reply-token recipient). So the only
classification that exists is **sender-address match (quarantine)**.

The question: does `landlord_engaged` flip on a **quarantined** inbound?

- **Option (a) — flip only when `quarantine_reason === null`** (genuine
  sender-matched reply). Stricter; "engaged = verified landlord". Risk:
  a genuine landlord replying from a slightly different address is
  quarantined → stays "never-engaged" → **auto-escalates** = the
  over-pursue (harmful) direction.
- **Option (b) — flip on any token-resolved inbound, quarantined or
  not** (mirrors the existing ball/clock flip exactly; one line, same
  semantics as line 123). Errs to the **under-pursue (safe)** side per
  the brief; a stranger who knows the secret token could suppress
  escalation, but that party can already inject a (quarantined)
  fake-landlord message today, so it adds no new exposure.

**My recommendation: (b)** — flip on the same condition that already
flips the ball, for consistency and the brief's "safe failure =
under-pursue" stance. Auto-responders/OOO (which pass quarantine, real
address) remain the accepted soft edge per the brief — tolerable at
pilot, and they fail safe (case becomes tenant-gated, under-pursues).
**This is the one definition call I want Charlie to rule explicitly**,
because it's the brief's flagged "critical definition question".

---

## D0.2 — the verdict branch

The only branch that returns a live escalation today is
**`SilenceClock::landlordSideVerdict()`**, specifically the
`SendEscalation` return at
[SilenceClock.php:252-260](app/Services/Silence/SilenceClock.php#L252-L260)
(reached when `silenceDays >= interval` and `counter < maxNotices`).
The change: gate that return on `$case->landlord_engaged`.

- **Never-engaged** (`false`) → `SendEscalation` exactly as today
  (no behaviour change).
- **Engaged** (`true`) → a **new verdict** that means "withhold the
  auto-send; the tenant must authorise." This needs a new
  `IntendedAction` case — proposed **`AwaitTenantAuthorisation`** —
  added to `App\Services\Silence\IntendedAction` (current cases, read
  off the sweep tally: `no_action`, `send_escalation`, `send_nudge`,
  `transition_dormant_intent`, `transition_exhausted_intent`,
  `resume_from_hold`). This is an **action-enum** addition, **not** a
  `CaseStatus` addition (see D0.3).

**Every consumer of the verdict (all must learn the new action):**

1. `SilenceSweep::handleCase()` `shouldExecute` match
   ([SilenceSweep.php:180-186](app/Console/Commands/SilenceSweep.php#L180-L186)).
2. `SilenceSweep::handleCase()` execute `match`
   ([:212-230](app/Console/Commands/SilenceSweep.php#L212-L230)) — needs
   a new arm that withholds + fires the tenant authorise-nudge
   (see D0.5) instead of `executeEscalation`.
3. The summary tally `$counts` array
   ([:139-146](app/Console/Commands/SilenceSweep.php#L139-L146)) — add
   the new key (snag #10's row-ID tally already in place).
4. `SilenceShadowLog` write (`intended_action` column) —
   [:576-590](app/Console/Commands/SilenceSweep.php#L568-L590) — stores
   the enum `->value`; no schema change, but the new value appears in
   shadow rows.
5. `SweepVerdict` (`App\Services\Silence\SweepVerdict`) — the DTO
   carries `intendedAction`; no shape change needed.
6. Tests asserting on verdicts/actions (D0.9).

**Exact shape (engaged branch, recommended):** when engaged and the
clock would have fired, return `AwaitTenantAuthorisation` with
`ballWith=Landlord`, `intendedLetterTemplate=LetterTemplate::forEscalation($counter+1)`
(so the held notice is identified and previewable), `escalationCounterValue=$counter`,
and a reasoning string. Counter is **not** incremented (no send yet) —
the D3 ratchet is preserved; authorisation later fires the real send
which does the increment via `SendCaseNotice`.

---

## D0.3 — new state or not? **NO new state.**

The held condition does **not** need a `CaseStatus`. The case stays in
`awaiting_landlord` (the landlord genuinely holds the ball — it is
their silence). "Tenant authorisation required" is a **derived**
condition, computed exactly as the sweep computes it:

> `landlord_engaged === true` AND the silence clock has expired AND
> `counter < max_notices` AND no newer landlord inbound has arrived.

The tenant case page already derives display state (e.g. the "Next
escalation" projection). The authorise prompt keys off the **same
`SilenceClock` verdict** (`intendedAction === AwaitTenantAuthorisation`)
— single source of truth, no stored flag beyond `landlord_engaged`, no
new sweep-exclusion wiring (your fact-find §6). This clears the brief's
high bar: a new state would have cost two exclusion lists
(`SilenceSweep` query + `NO_CLOCK_STATUSES`) and new transition edges.

**One structural cost to surface (the heart of the design).** The
held case is **landlord-ball**: after "thanks", the latest
`case_messages` row is the tenant's *outbound* reply, so
`SilenceClock::ballFor()` returns **Landlord**
([SilenceClock.php:102-113](app/Services/Silence/SilenceClock.php#L102-L113)).
But the existing nudge→dormancy tail runs **only** on the
tenant-ball branch (`tenantSideVerdict`,
[:281-352](app/Services/Silence/SilenceClock.php#L281-L352)), and the
only transition into `dormant` today is `awaiting_tenant_review →
dormant` ([RepairCase.php:156](app/Models/RepairCase.php#L156); there is
**no** `awaiting_landlord → dormant` edge). So "reuse the existing
dormancy tail" (brief D0.5) is **not free**. Two ways to honour it:

- **Option A (recommended) — keep the case landlord-ball in
  `awaiting_landlord`.** Add nudge-to-authorise + eventual dormancy
  logic to the *landlord-side* held branch: reuse the nudge **mail**
  machinery and the **nudge cadence settings** (`nudge.first_days` /
  `second_days` / `dormancy_days`) measured against the landlord clock,
  and add **one new transition edge** `awaiting_landlord → dormant`
  (event e.g. `case_dormant`/`escalation_unauthorised`). Dormant is the
  **existing state**, D11 revival applies unchanged — only the edge is
  new. Ball semantics stay honest.
- **Option B — force the case to tenant-ball.** Transition to
  `awaiting_tenant_review` so the existing tenant-side tail applies
  verbatim. **Problem:** `ballFor` reads message direction, and the
  last message is still the tenant's outbound reply → it would *still*
  read Landlord. Making B work needs a change to the ball-source rule
  (or a synthetic marker / status veto), and it overloads
  `awaiting_tenant_review`'s meaning ("landlord replied, read it" vs
  "escalation held, authorise it"). More invasive than it looks.

**Recommendation: Option A.** Minimal, honest, reuses dormant + D11,
costs one transition edge (not a new state). Flagging for ruling
because D0.5's "existing dormancy path" is, strictly, the existing
dormant *state* via a *new* edge — not the existing tenant-ball
*verdict path*.

---

## D0.4 — the tenant authorise action (D13 reuse)

D13's create-case flow is `store → preview → confirm`, session-staged,
where `confirm()` creates the case and fires the **first** send via
`SendCaseNotice::execute()`
([CaseController.php:241-385](app/Http/Controllers/CaseController.php#L241-L385)).

The held-escalation authorise is **simpler** because the case already
exists — no session staging, no photo promotion, no row creation:

- **Preview (GET):** render the next notice against the *existing*
  case's real data — `LetterTemplate::forEscalation($counter+1)` through
  the same `LetterTemplateRenderer` the preview already uses
  ([:319-331](app/Http/Controllers/CaseController.php#L319-L331)). Can
  reuse the `cases.preview` view / its letter-render partial.
- **Authorise (POST):** call `SendCaseNotice::execute($case,
  actorUserId: …)` with `tenantReplyBody=null`. Because the case is in
  `awaiting_landlord`, this hits the **existing `$isAutoEscalation`
  branch** ([SendCaseNotice.php:81, 191-220](app/Actions/SendCaseNotice.php#L191-L220)):
  it ratchets `current_stage`, writes the frozen escalation
  `case_messages` row, restarts the landlord clock, and (if we want the
  tenant heartbeat) the same notify-on-send applies. **This is the
  identical send the sweep would have auto-fired for a never-engaged
  landlord** — maximal reuse.

**Genuinely new:** a controller method pair
(`escalationPreview` + `escalationAuthorise`), two routes, a
**policy ability** (e.g. `authoriseEscalation`) that permits the tenant
**only** when the derived held condition (D0.3) is true, and the
authorise view (reuses the preview partial + a new `ui_copy` consent
row, D0.8).

**Reused wholesale:** `LetterTemplateRenderer`,
`LetterTemplate::forEscalation()`, `SendCaseNotice::execute()`
auto-escalation path, the preview blade.

---

## D0.5 — the tenant-side nudge for engaged-quiet

**Mail machinery: reusable.** The nudge/notification senders are
generic pre-rendered passthroughs (`dispatchNudge` /
`dispatchTenantNotification` →
`AutoEscalationTenantNotice`,
[SilenceSweep.php:441-526](app/Console/Commands/SilenceSweep.php#L441-L526)).
A "your landlord has gone quiet — authorise the next notice?" email is
a new **template row** (D0.8) sent through the same path, with a
magic-link to the D0.4 authorise screen.

**Triggering logic: NOT purely existing.** The existing nudge ladder
(`tenantSideVerdict`) fires only when `ballFor === Tenant`. The held
case is landlord-ball (D0.3), so the existing trigger does **not** cover
it. Under **Option A** the authorise-nudge is fired from the new
landlord-side held branch, reusing the nudge **cadence** (same three
settings) and **mail** path but with new triggering code. So: reuse
mail + thresholds; **new** trigger wiring. (Under Option B it would be
the existing trigger verbatim — at the ball-source cost in D0.3.)

**Unauthorised tail — confirmed as the existing `dormant` state with
D11 revival**, reached (Option A) via a **new** `awaiting_landlord →
dormant` edge. Same state, same revival window, same dormancy notice.
Not a new dormancy *concept* — a new edge into the existing one. I am
flagging the edge so "existing path" isn't over-read as "zero new
transition".

---

## D0.6 — notify-on-send (never-engaged) — CONFIRMED, not building

It exists and fires on **every** auto-escalation:
`SilenceSweep::executeEscalation()` calls
`dispatchAutoEscalationTenantNotice()` **unconditionally** after each
send ([SilenceSweep.php:297-301](app/Console/Commands/SilenceSweep.php#L297-L301)),
which routes to `dispatchTenantNotification(code:
'auto_escalation_tenant_notice', …)` (active-row idiom: no active row →
silent skip) ([:425-439](app/Console/Commands/SilenceSweep.php#L425-L439)).
Not threshold-gated — once per fired notice.

Confirmations the brief asks for:

- **Informational only, no action/button.** The mailable
  `AutoEscalationTenantNotice` is a pure subject+body passthrough with
  no actionable surface
  ([AutoEscalationTenantNotice.php:28-46](app/Mail/Notifications/AutoEscalationTenantNotice.php#L28-L46)).
  Any link in the rendered body is a magic-link *sign-in to view* (D12),
  not an authorise action — and it is template **data**, not control.
- **No `case_messages` row.** `dispatchTenantNotification` only
  `Mail::queue`s; it creates no message row. The mailable docblock makes
  this an explicit evidential invariant
  ([:11-27](app/Mail/Notifications/AutoEscalationTenantNotice.php#L11-L27))
  — a row here would inflate `SilenceClock::escalationCounter`.
- **No ball move, no tenant clock, no transition.** `executeEscalation`
  does only: `SendCaseNotice::execute` (the landlord letter — which
  correctly restarts the *landlord* clock and writes the escalation row)
  + the tenant notice (mail only) + a shadow row. The tenant notice
  adds none of these.

**Bonus — it auto-narrows to never-engaged for free.** After D15,
auto-escalation only runs for never-engaged landlords (engaged ones are
withheld at D0.2). So this existing every-send notice becomes
"never-engaged only" **by construction** — no change required. Pure
confirmation.

---

## D0.7 — backfill / existing cases — CONFIRMED non-issue at pilot

Pilot uses `migrate:fresh` from files; every case is created post-D15
with `landlord_engaged` defaulting false at creation
([CaseController.php:363-374](app/Http/Controllers/CaseController.php#L363-L374))
and flipped true by the chokepoint on first qualifying inbound. Nothing
to backfill.

**For the record (one line, no code):** default-false reproduces
*today's* behaviour for any un-backfilled case (everything
auto-escalates), so an already-engaged case migrated without backfill
would auto-escalate — the harmful direction, not "safe" as the brief
phrases it; it is "no worse than today", not benign. If real cases ever
existed, the backfill is trivially derivable:
`landlord_engaged = EXISTS(case_messages WHERE direction=inbound AND
sender_role=landlord [AND quarantine_reason IS NULL, per the D0.1
ruling])`. Goes in the design note; no migration now.

---

## D0.8 — template / settings rows (data, not finalised here)

New **wording rows** (seeded; phpMyAdmin-editable per the razor):

| Row | Type | Proposed code | Eyes |
|---|---|---|---|
| Tenant authorise-prompt nudge ("landlord went quiet — send next notice?") | `tenant_notification` (or `tenant_nudge`) | `authorisation_required_nudge` | Charlie |
| Authorise-screen consent copy (on the D0.4 view) | `ui_copy` | `escalation_authorisation` (parallels D13's `create_case_authorisation`) | **Charlie + solicitor pass** — it is per-send consent to send a formal legal letter in the tenant's name |
| (Optional) dormancy notice variant for the unauthorised tail | `tenant_notification` | reuse `dormancy_transition_notice` unless distinct wording wanted | Charlie |

**Settings:** recommend **no new rows** — reuse `nudge.first_days /
second_days / dormancy_days` for the authorise-nudge cadence. Flag only
if Charlie wants a *distinct* cadence for held-escalation vs ordinary
tenant silence.

Wording not drafted here per the brief.

---

## D0.9 — test surface

**New tests:**
1. Flag flips to true on first genuine landlord inbound; idempotent on a
   second; never resets. (+ a quarantined-inbound test pinned to
   whichever D0.1 option Charlie rules.)
2. **Headline regression:** "thanks" reply from `awaiting_tenant_review`
   on an **engaged** case → next sweep returns `AwaitTenantAuthorisation`,
   sends **no** escalation letter (closes fact-find §4).
3. Engaged-then-quiet: sweep withholds escalation and fires the
   tenant authorise-nudge (mail asserted, no `case_messages` row).
4. Tenant authorise → held notice fires via `SendCaseNotice`
   auto-escalation path: counter increments, frozen `case_messages` row
   written, landlord clock restarts.
5. Unauthorised engaged-quiet → falls to `dormant` (Option A edge) with
   D11 revival intact.

**Existing tests to re-assert (no weakened assertions):**
6. Never-engaged case still auto-escalates on landlord silence — update
   existing escalation tests to set `landlord_engaged=false` explicitly
   so the auto-send assertion still holds; add the engaged variant
   alongside.
7. Notify-on-send fires on every never-engaged auto-escalation, writes
   no `case_messages`, moves no ball, starts no clock — strengthen the
   existing 2b/3 assertion rather than relax it.

**Disposition targets (verify exact assertions at build):**
`tests/Feature/SilencePhase2b/SilenceSweepLiveTest.php`,
`tests/Feature/SilencePhase3/SweepTenantSideTest.php`,
`tests/Feature/SilencePhase2a/SilenceSweepCommandTest.php`,
and `tests/Feature/Phase4/WebhookInboundReplyTest.php` (flag-flip lives
on the inbound path). Baseline is **448**; the verdict-branch change
mutates the landlord-side path these assert, so each gets a
never-engaged setup line plus an engaged sibling — net additive.

---

## D0.10 — ride-along check vs open snags #12–19

Overlaps (flag, **do not fix** — separate batch):

- **#15** ("Next escalation" line shown in wrong state) and **#14**
  (stale `hold_until` display): D15 adds a new case-page display branch
  (the authorise-prompt) to the same escalation-projection area. Build
  it state-aware so it doesn't *worsen* #15; the snag itself stays in
  its batch.
- **#16** (hardcoded "Stage 1 of 4" denominator): the authorise preview
  shows notice/stage N; touches the same stage-display surface. No fix
  here.

No interaction: **#2/#7** (create-form landlord fields), **#19**
(tenant-reply attachments — D15 doesn't touch the reply path), **#12/#13**
(seed/gauge), **#8** (delivery webhooks), **#17/#18** (dev-tooling /
timestamp trap). The engagement flag is independent of the escalation
counter (counter = letters sent; engaged = inbounds received) — no
collision with the D3 ratchet invariant.

---

## Summary of decisions needed from Charlie before build

1. **D0.1 definition:** does `landlord_engaged` flip on a **quarantined**
   inbound? (Recommend **yes** — Option (b), mirrors the existing
   ball/clock flip, fails safe.)
2. **D0.3 mechanism:** **Option A** (keep landlord-ball in
   `awaiting_landlord`; new authorise-nudge logic + one new
   `awaiting_landlord → dormant` edge) vs Option B (force tenant-ball).
   (Recommend **A**.)
3. **Authority (§0):** approve adding a **D15** section to the
   authoritative design doc that supersedes/qualifies D7, made at build
   start.
4. **D0.8:** confirm reuse of existing nudge cadence settings (no new
   `settings` rows) and the solicitor pass on the authorise consent
   copy.

No new `CaseStatus`. No change to the escalation-counter invariant or
the evidential mail-only invariants. Stopping here for your ruling.

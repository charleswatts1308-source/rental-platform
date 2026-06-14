# CC REPORT — Phase 4 / D14 — Deliverable 0 (fact-find, no edits)

**Branch:** `d14-escalation-exhausted` (off `main` @ `b20061b`; tag
`pre-d14` on main). **No code edited.** Report only — stop for ruling.

**Baseline suite:** 462 (per the D15 close-out). New count estimated in
D0.8; confirmed at implementation.

Citations are `file:line` against the working tree at branch point.

---

## TL;DR — what needs a ruling before code

Three of these are **design-doc-vs-brief conflicts**; CLAUDE.md says the
design doc (`llcs-silence-model-design.md`) wins and I must flag, not
silently follow the brief. None block the rest of the report.

1. **[CONFLICT — must rule] The landlord closer letter.** Design doc D5
   (authoritative) says the exhaustion transition has a **send-point for a
   one-shot closing letter to the landlord**, fired if an active
   `exhaustion_landlord` template exists. That row **is seeded and active**
   (`exhaustion_landlord_closer`, [LetterTemplateSeeder.php:69-75](../database/seeders/LetterTemplateSeeder.php#L69-L75))
   and `SilenceClock` already anticipates it ([SilenceClock.php:120-124](../app/Services/Silence/SilenceClock.php#L120-L124)
   — "naturally excludes the future Phase 4 exhaustion_landlord row (which
   will have stage_at_send=NULL)"). **The D14 brief omits it** — its scope
   lists only a tenant notice + signposting page. **Rule:** does the
   transition also fire the landlord closer (per D5), or is it dropped for
   D14? See D0.6.

2. **[CONFLICT — minor, reconcilable] "Clock stops permanently."** D5:
   *"The clock stops permanently — no further automatic letters, ever."*
   The brief's allow-reply **restarts the clock**. Reconcilable: the D3
   ratchet (counter never resets, [SilenceClock.php:128-135](../app/Services/Silence/SilenceClock.php#L128-L135))
   means counter ≥ max forever, so no new escalation *letters* can fire
   even if the clock runs again — a revived-then-silent case just
   re-exhausts (never-engaged) or goes D15 held→dormant (if the revival was
   a landlord reply, which flips `landlord_engaged`). I read "no further
   automatic letters" as the binding invariant and "clock stops
   permanently" as describing letter behaviour, not literally freezing the
   timestamp. **Confirm that reading.** See D0.4.

3. **[CONFLICT — minor] Revival lands in `awaiting_landlord`?** The ruling
   says "revives to `awaiting_landlord`… the landlord re-engaging is the
   wanted outcome." Those are two different paths and the literal target
   only fits one. To mirror dormant exactly I need **both** revival edges
   (D0.4): a **tenant** web reply → `awaiting_landlord`; a **landlord**
   email reply → `awaiting_tenant_review` (same split dormant already has).
   The landlord-email path is the "re-engaging" one and it lands in
   `awaiting_tenant_review`, not `awaiting_landlord`. **Confirm both edges**
   (recommended) vs tenant-web only.

4. **[scope correction] The tenant notice already exists.** D0.6's "new
   row" is already seeded as `tenant_exhaustion_notice`
   ([LetterTemplateSeeder.php:76-83](../database/seeders/LetterTemplateSeeder.php#L76-L83)),
   type `tenant_notification`, active, wording apt. Reuse/repoint it; don't
   create a duplicate.

5. **[open question] Revival window.** Dormant gates the tenant-web reply
   on `dormancy.revival_days` (90). Exhaustion has no analogous setting and
   D5 implies revival is always open. **Propose: no window** — a reply
   either side always revives. Confirm.

---

## D0.1 — the intent today (where `transition_exhausted_intent` lives)

**Computed** in `SilenceClock::landlordSideVerdict`,
[SilenceClock.php:221-231](../app/Services/Silence/SilenceClock.php#L221-L231):

```php
if ($counter >= $maxNotices) {
    return new SweepVerdict(
        intendedAction: IntendedAction::TransitionExhaustedIntent,
        ballWith: BallPosition::Landlord, ...
        reasoning: "ladder exhausted (counter={$counter} >= max={$maxNotices});
                    would transition to escalation_exhausted (Phase 4)",
    );
}
```

**Exact condition.** This branch is reached only after:
- the no-clock / clock-start guards pass ([SilenceClock.php:161-190](../app/Services/Silence/SilenceClock.php#L161-L190));
- ball = landlord ([:195-197](../app/Services/Silence/SilenceClock.php#L195-L197));
- `silenceDays >= interval` (else NoAction, [:209-219](../app/Services/Silence/SilenceClock.php#L209-L219));
- **then** `counter >= maxNotices`.

So the firing predicate is exactly **clock expired AND counter ≥ max_notices
AND ball=landlord**. Note the engagement check sits *below* this
([:239-241](../app/Services/Silence/SilenceClock.php#L239-L241)): exhaustion
is evaluated **before** the `landlord_engaged` branch, so it fires for both
classes here — but per the brief's "How D15 tidied scope" an **engaged**
case can never *climb* to counter ≥ max (it's withheld → nudge → dormant
from counter < max), so in practice **only the never-engaged path reaches
exhaustion**. The ordering is benign (an engaged case at counter ≥ max
can't occur), but I flag it: the `counter >= maxNotices` check is not
gated on `!landlord_engaged`. **Recommendation:** leave the order as-is
(it's correct and matches the brief's "exhaustion is never-engaged-only"
by construction), and add the guard test in D0.8 to lock the invariant.

**Currently only LOGS, no transition.** The verdict is consumed by
`SilenceSweep::handleCase`. `TransitionExhaustedIntent` is **not** in the
`$shouldExecute` match ([SilenceSweep.php:183-194](../app/Console/Commands/SilenceSweep.php#L183-L194))
— it falls to `default => false`, so the row is written with
`executed:false` ([:196-200](../app/Console/Commands/SilenceSweep.php#L196-L200))
and nothing transitions. The enum case itself documents this
([IntendedAction.php:27-29](../app/Services/Silence/IntendedAction.php#L27-L29)):
"Until then the shadow sweep logs the intent as a marker — never
transitions." Confirmed: **log-only today.**

**`escalation.max_notices`** = **4** ([SettingSeeder.php:31](../database/seeders/SettingSeeder.php#L31);
default also 4 at [SilenceClock.php:83](../app/Services/Silence/SilenceClock.php#L83)).

---

## D0.2 — promote intent → transition

**New `CaseStatus`:** `EscalationExhausted = 'escalation_exhausted'` added
to [CaseStatus.php:5-14](../app/Enums/CaseStatus.php#L5-L14).

**New edge(s) IN — TRANSITIONS table** ([RepairCase.php:152-196](../app/Models/RepairCase.php#L152-L196)):
- `awaiting_landlord → escalation_exhausted` (event `case_exhausted`),
  added under the `'awaiting_landlord'` key, mirroring the D15
  `awaiting_landlord => dormant` edge added at [:161-168](../app/Models/RepairCase.php#L161-L168).
  This is the **only** source state — never-engaged auto-escalation runs
  exclusively from `awaiting_landlord` (SendCaseNotice's auto-escalation
  branch stays in `awaiting_landlord`, [SendCaseNotice.php:81,216-220](../app/Actions/SendCaseNotice.php#L216-L220)).

**Sweep wiring.** Add `TransitionExhaustedIntent` to the `$shouldExecute`
match in `handleCase` ([SilenceSweep.php:183-194](../app/Console/Commands/SilenceSweep.php#L183-L194))
gated on `ballWith === Landlord`, and a `match` arm in the transaction
block ([:220-242](../app/Console/Commands/SilenceSweep.php#L220-L242)) →
a new `executeExhaustionTransition()` mirroring `executeDormancyTransition`
([:433-458](../app/Console/Commands/SilenceSweep.php#L433-L458)):
`transitionTo(EscalationExhausted)` + dispatch the tenant notice (D0.6).
The post-lock supersede guard ([:256-281](../app/Console/Commands/SilenceSweep.php#L256-L281))
already covers it as a clock-based verdict (status + clock witnesses) — no
change needed there.

**Tests:** add positive `awaiting_landlord → escalation_exhausted` to
`CaseTransitionMapTest` ([:8-32](../tests/Feature/Phase2/CaseTransitionMapTest.php#L8-L32))
and **remove any** `… → escalation_exhausted` pair from the illegal
dataset (none exists yet — the value is new — so nothing to remove for the
inbound edge; see D0.8 for the new same-status/terminal coverage the new
enum value pulls in automatically).

---

## D0.3 — new state + sweep exclusion (the D5/§6 inert pattern)

`escalation_exhausted` must be excluded in **both** gates, exactly as
`resolved`/`abandoned`/`dormant` are:

1. **Sweep query** ([SilenceSweep.php:101-107](../app/Console/Commands/SilenceSweep.php#L101-L107)):
   add `CaseStatus::EscalationExhausted` to the `whereNotIn('status', […])`.
   The case is never fetched, so never evaluated.

2. **`NO_CLOCK_STATUSES`** ([SilenceClock.php:60-66](../app/Services/Silence/SilenceClock.php#L60-L66)):
   add `CaseStatus::EscalationExhausted`. `ballFor()` returns null
   ([:96-100](../app/Services/Silence/SilenceClock.php#L96-L100)) → `evaluate()`
   short-circuits to NoAction ([:163-173](../app/Services/Silence/SilenceClock.php#L163-L173)).
   Belt-and-braces with gate 1, identical to how `Dormant` sits in both.

**Stance-independence:** the stance flag (D0.5) is a separate column read
by nothing in the sweep/clock, so inertness holds at all three stance
values — there is no code path where stance is consulted. Confirmed
sweep-inert regardless of stance.

---

## D0.4 — allow-reply revival

An exhausted case is **always never-engaged** (`landlord_engaged = false`),
so this is the reconciliation point with D15. There are **two** reply
triggers, mirroring dormant's existing split:

**(A) Landlord replies by email** — D5's "late arrival via webhook"; the
wanted "re-engaging" outcome. Path: `HandleInboundReply`. Today
`STATES_THAT_TRANSITION = {AwaitingLandlord, OnHold, Dormant}`
([HandleInboundReply.php:50-54](../app/Actions/HandleInboundReply.php#L50-L54))
→ transitions to `AwaitingTenantReview` ([:138-145](../app/Actions/HandleInboundReply.php#L138-L145)).
To enable:
- add `EscalationExhausted` to `STATES_THAT_TRANSITION`;
- add edge `escalation_exhausted → awaiting_tenant_review` (event
  `inbound_received`), mirroring dormant's `dormant → awaiting_tenant_review`
  ([RepairCase.php:189-190](../app/Models/RepairCase.php#L189-L190)).

This path **flips `landlord_engaged` → true** ([:127-134](../app/Actions/HandleInboundReply.php#L127-L134))
and restarts the clock with ball=tenant. **This is correct and desirable:**
the landlord is now engaged, so if the revived case later goes quiet on the
landlord side it follows the D15 held→dormant route, never re-exhausts.
**Without adding `EscalationExhausted` to `STATES_THAT_TRANSITION`** a
landlord email would hit the `else` branch ([:146-160](../app/Actions/HandleInboundReply.php#L146-L160)):
message recorded, `landlord_engaged` set, clock restarted, ball=tenant —
**but no transition**, leaving a sweep-inert case with the ball wrongly on
the tenant and the landlord's reply unsurfaced. So this edge is **required**,
not optional.

**(B) Tenant replies via web** — restarts correspondence from the tenant
side. Path: `CaseController::reply` → `SendCaseNotice` tenant-reply branch
→ `awaiting_landlord`. To enable:
- `RepairCasePolicy::reply` ([RepairCasePolicy.php:52-65](../app/Policies/RepairCasePolicy.php#L52-L65)):
  add an `EscalationExhausted` arm returning `true` (no revival window —
  see TL;DR #5);
- `SendCaseNotice::assertEntryStatusAllowed` tenant-reply whitelist
  ([SendCaseNotice.php:249-265](../app/Actions/SendCaseNotice.php#L249-L265)):
  add `EscalationExhausted`;
- edge `escalation_exhausted → awaiting_landlord` (event `tenant_replied`),
  mirroring `dormant → awaiting_landlord` ([RepairCase.php:187-188](../app/Models/RepairCase.php#L187-L188)).

This path **leaves `landlord_engaged = false`** (a tenant reply doesn't
engage the landlord), so the revived case can climb again — but counter is
already ≥ max, so the clock can only re-fire `TransitionExhaustedIntent`
(no new letters), re-exhausting from `awaiting_landlord`. Consistent with
"no further automatic letters."

**Engagement-flag reconciliation (the flag the brief asked me to surface):**
- landlord-email revival → `landlord_engaged` flips true (path A); ✓
  consistent with D15.
- tenant-web revival → stays false (path B); ✓ the case remains
  never-engaged and re-exhausts rather than going D15-held.

**This is unambiguous to me** — both edges, mirroring dormant — **but it
contradicts the ruling's literal "revives to `awaiting_landlord`"** for
path A (lands in `awaiting_tenant_review`). Flagging per TL;DR #3 for your
explicit sign-off before I wire both.

---

## D0.5 — the stance flag (label-only)

**Column:** `cases.exhausted_stance` — `string`, nullable, **default null**,
values `null | 'abandoned' | 'unresolved'`. Migration mirrors the D15
`landlord_engaged` add ([2026_06_13_090000_add_landlord_engaged…](../database/migrations/2026_06_13_090000_add_landlord_engaged_to_cases_table.php)).
Add to `$fillable` + a cast is optional (plain string is fine; no enum cast
needed, but an `App\Enums\ExhaustedStance` backed enum + cast is the
cleaner idiom and keeps the three values in one place — **recommend the
enum**). Add to `RepairCase` ([RepairCase.php:41-75](../app/Models/RepairCase.php#L41-L75)).

**Label-only guarantee.** The column is referenced by **nothing** in
`SilenceClock`, `SilenceSweep`, `SweepVerdict`, `transitionTo`, or the
TRANSITIONS map. Inertness is therefore structural, not conditional. The
D0.8 test asserts a stance change moves no sweep outcome.

**Where the tenant sets it:** a new POST action on the exhausted case view.
New route `POST /cases/{slug}/stance` → `CaseController::setStance`, modelled
on `resolve`/`abandon` ([CaseController.php:226-259](../app/Http/Controllers/CaseController.php#L226-L259))
**but it does NOT `transitionTo`** — it validates `in:abandoned,unresolved`
(nullable to allow unset) and writes the column via `$case->save()`. Note:
the `booted` updating guard only fires on a dirty **status**
([RepairCase.php:34-38](../app/Models/RepairCase.php#L34-L38)), so a plain
`exhausted_stance` write is unaffected. New policy method `setStance`
(ownership + `status === EscalationExhausted`).

**Where the label renders:** the status card / action panel. Add an
`@case(CaseStatus::EscalationExhausted)` arm to `_action_panel.blade.php`
([_action_panel.blade.php:13-36](../resources/views/cases/_action_panel.blade.php#L13-L36))
showing the end-of-road copy + a small stance selector (radio/select →
the new form), and render the chosen label as a badge near the status badge
([show.blade.php:41-44](../resources/views/cases/show.blade.php#L41-L44)).
The reply form already appears for exhausted (path B) because the policy
`reply` arm (D0.4) returns true.

**Ride-along (fold in here):** the projected "Next escalation" date block
([show.blade.php:61-64](../resources/views/cases/show.blade.php#L61-L64))
keys off `ball_with === 'landlord'` + a started clock — after exhaustion
both are still set (ball stayed landlord, clock not cleared), so it would
render a **phantom future escalation date that never fires**. Suppress it
for `EscalationExhausted` exactly as it's already suppressed for
`authorisationPending` (same line's `!($authorisationPending ?? false)`
guard). In scope for the exhausted view.

---

## D0.6 — the notice row (REUSE, don't create)

The tenant notice **already exists**: `tenant_exhaustion_notice`, type
`tenant_notification`, active, with apt wording
([LetterTemplateSeeder.php:76-83, 256-278](../database/seeders/LetterTemplateSeeder.php#L76-L83)).
The sweep's `dispatchTenantNotification` looks templates up by `code`
([SilenceSweep.php:604-619](../app/Console/Commands/SilenceSweep.php#L604-L619)),
so `executeExhaustionTransition` dispatches `code='tenant_exhaustion_notice'`
— **no new row needed.** Action: **repoint** its body to link the
signposting page (D0.7) and re-list it for the **solicitor pass** (it
predates this brief and was never solicitor-reviewed).

**Merge fields it needs — all available** via `dispatchTenantNotification`'s
var bag ([SilenceSweep.php:629-639](../app/Console/Commands/SilenceSweep.php#L629-L639)):
`tenant_name`, `landlord_name`, `case_reference`, `property_address`,
`issue_description`, `response_days`, `notice_number`, `magic_link` — every
one is in `LetterTemplateRenderer::WHITELIST` ([:35-48](../app/Services/LetterTemplateRenderer.php#L35-L48)).
The body currently uses `{{tenant_name}}` + `{{magic_link}}` only — both
present. **No new merge field invented** (the `last_reply_date` lesson):
the signposting link is reached via `magic_link` → case page → the
exhausted panel's link to the members-wall page (D0.7). If you instead want
the email to **deep-link** the signposting page directly, that needs a new
whitelist var (`signposting_url`) wired in the dispatch — flagged as the
alternative; **recommend the magic-link-to-case-page route** to avoid a new
field.

**[CONFLICT — D0.6 ↔ D5, see TL;DR #1]** D5 also wants a **landlord** closer
(`exhaustion_landlord` / `exhaustion_landlord_closer`, active). If ruled
in, `executeExhaustionTransition` additionally renders + sends it via the
`SendCaseNotice`/`CaseNotice` path **with `stage_at_send = NULL`** (so the
counter predicate at [SilenceClock.php:128-135](../app/Services/Silence/SilenceClock.php#L128-L135)
excludes it) and mints a fresh reply token (the landlord could reply → feeds
D0.4 path A). This is a real outbound `case_messages` row (evidential), so
it is NOT subject to the mail-only nudge invariant. **Awaiting your ruling.**

---

## D0.7 — the signposting page (members-wall, stubbed)

**Route:** add to the **auth-only** members group
([web.php:64-68](../routes/web.php#L64-L68)) — e.g.
`Route::get('/escalation-routes', fn () => view('members.escalation-routes'))->name('escalation-routes')`.
That group already sits behind `['auth','verified']` ([:31](../routes/web.php#L31)),
which is the members-wall gate ("substantive content behind the members
wall"). **Not in public nav** — the public members block
([:72-80](../routes/web.php#L72-L80)) is left untouched, and no nav link is
added (the layout nav is the public set per MEMORY).

**View:** `resources/views/members/escalation-routes.blade.php`, plain
Bootstrap, shape-only placeholder: a short intro ("if the landlord won't
engage, your documented trail supports these routes…") and empty/stub
sections for ombudsman / council EH / court — **no named bodies,
thresholds, or deadlines** (solicitor-deferred, matching the brief and
D5's "content deferred permanently — edit rows, not code").

**Linked from** the exhausted tenant notice via the case page (D0.6) and
from the exhausted action-panel arm (D0.5). D5 wants signposting "shown on
the case page at this state" — satisfied by the action-panel link; the
members-wall page is the fuller destination. (Minor D5 divergence, TL;DR
none-blocking.)

---

## D0.8 — test surface

New file set under `tests/Feature/Phase4/` (the dir already exists —
`DormantWakeTransitionTest.php`). Enumerated:

1. **Headline transition.** Never-engaged case, ball=landlord, counter at
   max (4), clock expired → sweep executes → status `escalation_exhausted`;
   `executed=1`; shadow row `intended_action=transition_exhausted_intent`;
   tenant notice queued; (if D5 closer ruled in) landlord closer sent with
   `stage_at_send=null` and counter unchanged.
2. **Headline inertness ×3 stances.** Exhausted case at each of
   `null / abandoned / unresolved` → sweep → not even fetched (whereNotIn)
   / NoAction; assert **no** escalation, **no** nudge, **no** silence
   accrual, no Mailgun send, no shadow `executed`. Three dataset rows.
3. **Allow-reply revival — tenant web.** Reply from `escalation_exhausted`
   → `awaiting_landlord`; fresh token minted + old superseded, frozen
   `case_messages` row, clock restarted, ball=landlord (D11-shape);
   `landlord_engaged` stays false.
4. **Allow-reply revival — landlord email.** Inbound via `HandleInboundReply`
   from `escalation_exhausted` → `awaiting_tenant_review`; `landlord_engaged`
   flips true; clock restart; ball=tenant. (Both edges per D0.4.)
5. **Stance is label-only.** Set each stance, run sweep, assert identical
   verdict/outcome to unset — stance changes **no** machine behaviour.
6. **Guard: engaged never exhausts.** An engaged case driven toward
   silence with counter < max goes the D15 held→dormant route and **never**
   reaches `escalation_exhausted` — locks "exhaustion is never-engaged-only".
7. **Transition-map / illegal-dataset updates.**
   - `CaseTransitionMapTest`: add positives `awaiting_landlord →
     escalation_exhausted`, `escalation_exhausted → awaiting_landlord`,
     `escalation_exhausted → awaiting_tenant_review`, and the
     terminal-style negatives the new enum value implies.
   - `CaseIllegalTransitionTest` ([…/CaseIllegalTransitionTest.php:18-31](../tests/Feature/Phase2/CaseIllegalTransitionTest.php#L18-L31)):
     the `it('rejects same-status no-op transitions')` and
     `it('rejects all transitions out of terminal statuses')` datasets in
     `CaseTransitionMapTest` iterate `CaseStatus::cases()`, so adding the
     enum value **auto-extends** them — verify `escalation_exhausted →
     escalation_exhausted` is rejected and that exhausted is **not** terminal
     (it has two legal out-edges), adjusting the terminal dataset which
     currently hard-codes only `{resolved, abandoned}`.
8. **No-active-template safety.** Exhaustion transition with no active
   `tenant_exhaustion_notice` (and, if ruled in, no `exhaustion_landlord`)
   → transition still happens, mail skipped silently (active-row idiom).

**No weakened assertions.** **Baseline 462; estimate +14–18 → ~476–480**
(headline + 3 inertness + 2 revival + stance + guard + ~4 map/illegal +
template-safety). Exact count in the implementation report.

---

## D0.9 — ride-along (flag, don't fix)

- **Design-doc conflicts** — TL;DR #1 (landlord closer), #2 (clock-stops),
  #3 (revival target). These are the CLAUDE.md "flag, don't silently
  follow" items.
- **Phantom "Next escalation" date** on the exhausted case page
  ([show.blade.php:61-64](../resources/views/cases/show.blade.php#L61-L64))
  — folded into D0.5 (suppress for exhausted), not a separate fix.
- **D15 verdict-branch ordering** ([SilenceClock.php:221-241](../app/Services/Silence/SilenceClock.php#L221-L241))
  — exhaustion is checked above the `landlord_engaged` branch. Benign (an
  engaged case can't reach counter ≥ max), locked by the D0.8 guard test.
  No reorder.
- **Snags #12–#20:** no overlap. #20 (D9 dark-mode header contrast) touches
  the renderer header block the exhausted notice also uses, but it's
  cosmetic and separate — not handled here.
- **`exhaustion_landlord_closer` wording** ([LetterTemplateSeeder.php:233-254](../database/seeders/LetterTemplateSeeder.php#L233-L254))
  is a landlord-facing legal letter → **solicitor-gated** for production if
  ruled in (alongside `tenant_exhaustion_notice` and the signposting page).

---

## Scope-wall confirmation

In scope, untouched, and acceptance as the brief states them. No Phase 5,
no success-recording strand, no D15 held-path change, no snag fixes.
**Stopping here for the ruling** — chiefly the four TL;DR conflicts.

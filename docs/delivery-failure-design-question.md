# Delivery failure vs silence — a design question

**Standalone brief.** Written 2026-07-18 to be readable without repo
access. Context for snags #24 and #25 in `llcs-snagging-list.txt`.

---

## What the product does

renters.rent is a tenant-facing service that writes to a landlord on a
tenant's behalf about a repair, and then **escalates on silence**. If the
landlord does not reply within an interval, the system sends the next,
firmer letter in a fixed ladder. If the landlord never engages at all,
the ladder runs to exhaustion and stops.

The output the tenant is really buying is **an evidential record**: a
dated, frozen sequence proving they notified their landlord, what they
said, when, and that the landlord did not respond. That record is
intended to be usable in a dispute.

So the load-bearing claim is:

> "Your landlord was served on the 12th and has not responded in 14 days."

## How that's built (the parts that constrain any fix)

- **Letters are frozen at send time.** An outbound letter is written to
  `case_messages` with its rendered `subject` and `body_raw`, and the
  mailable reads those verbatim — it never re-renders. What was sent can
  never retrospectively change.
- **Each outbound row also stamps `to_address_raw`** — the recipient at
  the moment of sending — plus `sent_at`. So a sent letter is
  **self-contained**: the row records where it went, independent of any
  later state.
- **The escalation counter is derived, never stored, never reset.**
  It is a COUNT of outbound system rows with a non-null stage marker.
  This "ratchet" is deliberate: the ladder cannot be rewound.
- **The silence clock starts at send** and restarts on each new outbound
  letter or inbound reply. A daily sweep asks, per case, "has the
  configured interval of silence elapsed?" and sends the next rung if so.
- **Time is injected** throughout, so real, simulated and test runs use
  one code path.

The design treats these as invariants. Anything proposed here has to
respect them or explicitly argue for changing them.

## The gap

Mailgun's **inbound** route is consumed and carefully built — signature
verification, token resolution, quarantine of anything suspicious.

Mailgun's **delivery-event** webhooks are not consumed at all:
`permanent_fail` (hard bounce), `temporary_fail`, `complained`,
`unsubscribed`. Nothing in the application ever learns that an outbound
letter failed to arrive.

Consequence: **a letter that bounced and a letter that was ignored are
indistinguishable to the system.** The row is written, `sent_at` is
stamped, the clock starts, the ladder proceeds — and the product states
the load-bearing claim above with full confidence when nobody was ever
served. The tenant is left holding a meticulous dated record of having
notified no one, which is exactly the artefact they'd rely on.

This is not primarily a data-entry problem. A mistyped address is the
most likely cause, but the same silent failure hits a landlord who
changed letting agent, a full mailbox, an expired domain, or a spam
block — all of which happen to tenants who typed everything correctly.
So "be careful entering the email" is not a fix.

### What a fix would actually require

Mailgun makes the detection easy — it reads the SMTP failure and fires a
webhook, and the existing signature-verification middleware is reusable.
Correlating that event back to the right letter is also easy: the
mailable is constructed with the message row itself, and Laravel's
Envelope supports metadata which the Mailgun transport maps to Mailgun
custom variables, returned on every delivery event. That is one line
beside the tagging line already present.

So the plumbing is roughly a day: the metadata line, a migration adding
delivery-status columns, a delivery-event webhook controller mirroring
the existing inbound one, a route, and tests. Then a few more hours to
notify the tenant and surface "undelivered" on the case — with one hard
constraint: **that notification must be mail-only and must not create a
message row**, because outbound system rows with a stage marker ARE the
escalation counter, and adding one would inflate the ladder.

**But it would not close the gap completely.** A hard bounce means the
address does not exist, and that is catchable. A typo that lands on a
*real* mailbox belonging to someone else delivers cleanly, and Mailgun
correctly reports success. Bounce handling catches "went nowhere"; it
cannot catch "went somewhere wrong". Any claim the product makes about
delivery has to stay honest about that limit.

The code, in other words, is not the expensive part. The rulings below
are.

## Question 1 — what should the silence clock do on a hard bounce?

Counting silence against an address that bounced is counting nothing.
The obvious answer is "suspend the clock, mark the case undelivered,
tell the tenant."

But the design deliberately starts the clock at send and treats the
counter as a ratchet, and the reasons for that were about **not letting
escalation depend on the tenant's attention** — an earlier version made
escalation contingent on a tenant judgement step and that was removed on
purpose. So a fix that says "the case pauses until the tenant fixes
something" is reintroducing exactly the dependency that was designed
out, and needs to be argued for rather than assumed.

Sub-questions that matter:
- Hard bounce (`permanent_fail`) vs soft (`temporary_fail`) — the first
  is definitive, the second may resolve on retry. Treat differently?
- If the clock suspends, what does the tenant see, and what happens if
  they never act? Does the case go dormant, or sit suspended forever?
- Does a bounced letter still count toward the escalation counter? It
  was sent, but it was not received. The counter is currently a count of
  *sends*, not of *deliveries*.

## Question 2 — should correcting the landlord address reset the clock?

There is currently no way to change a case's landlord email after the
first letter goes out (snag #24). Adding one is evidentially safe,
because sent rows carry their own `to_address_raw` — repointing the
contact changes only where FUTURE letters go and cannot rewrite what the
record says was already sent.

But: if the address is corrected, the new recipient has never been given
a chance to respond. Arguably the clock should restart and the counter
should reset — except the counter is explicitly a never-reset ratchet,
and letting a correction rewind the ladder is a lever that could be
misused or simply produce a misleading record.

What should the evidential record show? Options seem to be: the
correction as an event in the timeline with the ladder continuing; or a
clean restart; or a new case linked to the old one.

## What a good answer looks like

Not code. A ruling on:

1. Should a hard bounce suspend the silence clock, and if so what is the
   tenant-facing behaviour and the failure mode if they never act?
2. Do bounced letters count toward the escalation ladder?
3. On address correction, does the ladder continue, restart, or fork?
4. Is there a reading where the current behaviour is actually defensible
   — i.e. the record honestly says "we sent to X on this date" and it is
   the tenant's job to supply a correct address?

That last one is a genuine possibility and shouldn't be dismissed just
because the alternative sounds more helpful.

---

# RULINGS — Charlie, 2026-08-09

Nine rulings, taken in sequence. These close the questions above and
serve as the **brief** for the #25 build; the D0 report cites this
section. No code has been written against them.

## R1 — the record EXTENDS, it does not break (answers Q4)

Reading B: the product's claim is that the landlord was **served**, not
merely that we posted something. But a bounce does not invalidate the
record — it **adds to it**. Each of these stays separately true and each
gets its own entry:

> we sent it · we detected a bounce · we informed the tenant · we stopped
> · the tenant gave a new address · the case restarted

This is a stronger position than the question anticipated, because the
record never has to retract anything. It also means the tenant-facing
wording can be wholly factual at every step.

## R2 — hard bounce stops; soft bounce is recorded silently

`permanent_fail` stops the case and notifies the tenant.
`temporary_fail` is captured against the message and visible in the
record, but produces **no tenant-facing action** — Mailgun retries these
on its own schedule and most deliver. Alarming a tenant about a full
mailbox, and asking them to "fix" a correct address, would manufacture a
crisis out of a hiccup.

*Build check:* confirm from a real payload whether Mailgun converts an
exhausted soft bounce into a `permanent_fail` when it finally gives up.
If it does, a permanently soft-bouncing address is covered for free. If
it does not, that is a residual gap and must be recorded as one.

## R3 — a letter-1 bounce forks; the old case closes

The copy-and-start-over model. The old case keeps its single bounced
letter (frozen and correct), closes terminal, and a new case is created
carrying the same property, category, severity, description and photos.

The new case starts with **zero message rows**, so its derived counter is
genuinely 0 and its first letter is stage 1. Nothing is reset, nothing is
rewound — the numbers are simply true. **D3's ratchet is untouched.**

**No link between the two cases.** A relationship nothing consumes is not
worth modelling; the closed case explains itself through its own events.

## R4 — mid-flow bounces are OUT OF SCOPE

A bounce after one or more letters have been delivered is rare and hard
to get right: continuing honestly would require re-serving the bounced
rung, which would write a second row at the same stage and inflate the
row-counted ladder. Deliberately deferred.

What still happens mid-flow, because it is free from the same machinery:
**detect, record on the message, notify the tenant, stop the case.** What
is *not* built is the automated "correct the address and continue". The
tenant's route is to raise a fresh case.

*Consequence:* #25 no longer depends on snag #24, and **the escalation
counter is not modified in any way** — no `COUNT(DISTINCT)`, no
delivery-state dependency, no new entry shape in `SendCaseNotice`.

## R5 — the transition is IMMEDIATE, with no waiting state

"A bounce is a bounce — it's not going to change." The case transitions
on receipt of the event. No paused state that could sit forever, no
timeout rule, no case held open awaiting an action that may never come.

## R6 — capture successes as well as failures

`delivered` events are captured too. They carry the accepting server's
SMTP code, response string, MX host and attempt number, turning "we sent
a letter" into a contemporaneous, signature-verifiable record of an
**external** event — and removing the dependency on Mailgun's one-day log
retention, which is currently the only ground truth in existence.

## R7 — complaints are terminal WHEREVER they occur

Corrected during the session: a complaint is not a letter-1 concern. It
can arrive at any rung, and it must **never** prompt a copy-and-restart.
There is no address problem for a new address to fix, and routing around
someone who has explicitly rejected the mail would be both wrong and
futile.

Note the evidential asymmetry, which is the interesting part: a bounce
proves the letter went **nowhere**; a complaint proves the **opposite** —
it arrived, was seen, and was rejected. That is evidence of receipt, and
close to the strongest available short of a reply.

`unsubscribed` is out of scope by definition: the landlord never
subscribed to anything.

*Build check:* confirm no `List-Unsubscribe` header is being attached.
Mailgun can add one from domain settings regardless of intent, and on a
statutory notice that would hand a landlord a one-click opt-out from
being served, followed by silent suppression of everything after it.

## R8 — failure reasons are NOT case statuses

The decisive ruling on modelling. **Two separate collections:**

| collection | home | nature |
|---|---|---|
| delivery outcome | `case_messages` | a fact about ONE message: which letter, which address, which SMTP response, when |
| lifecycle state | `cases.status` | where the CONVERSATION stands |

Delivery status, timestamp and detail go on the message row. **One** new
terminal case status means "email contact with this landlord cannot
continue"; *why* is read from the message, never encoded in the status.

The reason this matters is cost asymmetry, established during the
session and evidenced in R9: **statuses are expensive and dangerous,
message columns are cheap and inert.** Every status must be classified in
the sweep denylist, the clock's `NO_CLOCK_STATUSES`, and every display
predicate — and each one fails open if forgotten. No predicate branches
on a delivery column, so it carries no such risk.

Rejected on the way: reusing `abandoned` (tenant-initiated — would
misattribute the decision to the tenant) and two statuses
`undeliverable` + `complained` (reads better in a list, but doubles the
fail-open surface).

**Status name:** `contact_failed`. It must be true of a bounce *and* a
complaint, which are opposites — `undeliverable` fails that test, since a
complaint means the letter *was* delivered. What the two share is that
email contact has stopped and cannot resume.

## R9 — display: list shows the status, case page explains it

The all-cases list carries the status as a short label; the individual
case page gives the full explanation, read from the message's delivery
status so it differs by cause. The enum value and the displayed text need
not match — `contact_failed` can render as "Closed — could not contact
landlord".

## Wording discipline (binding on anything user-facing or in the record)

Extends the discipline already recorded in snag #25:

- **"delivered" means ACCEPTED BY A SERVER** — not read, not seen.
- **The accepting server is the MX for the RECIPIENT'S DOMAIN** (Google,
  Microsoft), *not* "the landlord's mail server". Name the MX host from
  the payload.
- **A complaint is "reported as spam", not "the landlord marked it as
  spam".** The event reaches us from the recipient's mailbox provider via
  a feedback loop; it tells us a complaint was registered against the
  message, not which person clicked what. On a record intended for a
  dispute, that difference matters.
- A bounce catches "went NOWHERE", never "went SOMEWHERE WRONG". A typo
  landing on a real stranger's mailbox delivers cleanly and is reported
  as success. Any claim about delivery must stay honest about that limit.

## Open build items (decided to be decided later, not overlooked)

1. **What "stop the case" means mechanically for a mid-flow bounce or a
   complaint.** The letter-1 path has `contact_failed`, terminal. Mid-flow
   is not terminal and not undeliverable. Reuse `on_hold`, or add a
   paused state? To be settled at D0.
2. The two Mailgun payload checks in R2 and R7.
3. Whether the tenant notification for a complaint should say anything
   about next steps. Email as a channel is finished for that landlord;
   the realistic route is off-platform (post, council, solicitor). The
   notification must not imply we can keep trying.

## Prerequisite

**The fail-open status trap (snag #47) should be closed BEFORE
`contact_failed` is added.** Adding a status to the enum today silently
opts it INTO sweeping and INTO having a silence clock — so the fix for
#25 would, if that step were forgotten, escalate the very cases it just
declared unreachable.

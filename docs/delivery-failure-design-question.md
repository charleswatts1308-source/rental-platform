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

# Mailgun delivery-event payloads — observed reference

Captured 2026-08-23 on renters.rent via the temporary capture endpoint
(Release 2, `feature/delivery-capture`). **These are Mailgun's own test
payloads**, fired from the dashboard's "Test webhook" button — synthetic
values (`alice@example.com`, `my_var_1`), but the *structure* is
Mailgun's real one.

This file exists so the #25 receiver is built against observed bytes
rather than documentation. Written up from the capture log before it was
deleted from the box.

**Status of the real sends:** not yet done at time of writing. What they
still buy is listed at the bottom — mainly whether our own
`X-Mailgun-Variables` header survives Symfony's Mailgun transport.

---

## 1. The signature is NESTED — D0.2 confirmed

Every event arrives as:

```json
{
  "signature": {
    "token": "e6bfdcd34b66c293545a1969591ce2432c1b3a5df9c8592d5d",
    "timestamp": "1787507330",
    "signature": "c593417313ac1c18c77d1b143d42930490e379dfe1fb44e867c57426c804ad6b"
  },
  "event-data": { ... }
}
```

Inbound routing carries `timestamp` / `token` / `signature` **flat at top
level**. Event webhooks nest them under `signature`. Two different shapes,
exactly as the D0 inferred.

**Consequence, now proven rather than suspected:**
`VerifyMailgunSignature` reads the flat fields, so an event hits its
"Missing signature fields" branch and returns **406**. Mailgun treats 406
as a deliberate refusal and **never retries**. Events would be discarded
permanently and silently while the webhook looked healthy in the
dashboard.

A second middleware is therefore confirmed necessary. Verification is
HMAC-SHA256 over `timestamp` concatenated with `token`, in that order,
no separator.

## 2. Content type is `application/json`

Captured `content_type: application/json`, sent by `Go-http-client/1.1`.

The inbound route is form-encoded. So the receiver cannot reuse the
inbound parsing either — this is a **second** shape difference beyond the
signature, and one the D0 did not anticipate.

## 3. There is no `permanent_fail` event type

This is the trap. The event name is `failed` for both hard and soft
failures; what separates them is `severity`:

| event | severity | reason | meaning |
|---|---|---|---|
| `failed` | `permanent` | `suppress-bounce` | dropped, never attempted |
| `failed` | `temporary` | `generic` | soft failure, will retry |
| `delivered` | — | — | accepted by the receiving MX |
| `complained` | — | — | recipient marked it as spam |

**The receiver must branch on `severity`, not on event name.** A parser
keyed off an event called `permanent_fail` — which is what the Mailgun
webhook subscription UI calls it — would match nothing at all, silently.

## 4. The suppressed-address case, which is the important one

```json
{
  "event": "failed",
  "severity": "permanent",
  "reason": "suppress-bounce",
  "delivery-status": {
    "attempt-no": 1,
    "code": 605,
    "description": "Not delivering to previously bounced address",
    "session-seconds": 0
  }
}
```

Note `session-seconds: 0` and the 605 code — there was no SMTP
conversation at all. The message was dropped before any delivery attempt.

This is the state the two July suppressions are already in: our API call
succeeds, we record a letter sent, nothing is transmitted, and nothing
ever will be. The silence clock then runs and the ladder ratchets against
a letter that never left the building.

A fresh bounce and a dropped resend are distinguishable by `reason`.
Both are `failed`/`permanent`, so severity alone is not enough to tell
them apart — and they need different responses.

## 5. Fields useful for correlation

- **`event-data.user-variables`** — an object, echoed back on every
  event. This is where `case_message_id` should appear (see commit
  a70065b, `CaseNotice::headers()`).
- **`event-data.message.headers.message-id`** — the RFC message id.
  Fallback correlation key if user-variables ever fails us. Note
  `case_messages.mailgun_message_id` already exists and is indexed, but
  is currently written only on the INBOUND path.
- **`event-data.recipient`** — necessary but NOT sufficient on its own:
  the same landlord address legitimately receives several letters across
  the escalation ladder.
- **`event-data.id`** — Mailgun's own event id. Worth storing for
  idempotency; webhooks can be delivered more than once.
- **`event-data.timestamp`** — Unix, with fractional seconds.

## 6. Other observations

- `domain.name` is `mg.renters.rent`, so a receiver can reject events
  for any other domain as a cheap safety check.
- `flags.is-test-mode` and `flags.is-system-test` distinguish real
  traffic from dashboard tests.
- `storage.url` gives an API URL for retrieving the stored message.
- `tags` carries our environment tag (`production` / `staging`), set in
  `CaseNotice::envelope()`. A second guard against a staging event being
  applied to a production case.

---

## What the real sends still buy

The shape questions above are **settled**. Outstanding:

1. **Does our `X-Mailgun-Variables` header survive Symfony's Mailgun
   transport?** The captured `user-variables` are Mailgun's placeholders,
   not ours. If a real send comes back without `case_message_id`, the
   correlation needs a different mechanism — and better to learn that now
   than after the receiver is built on it.
2. **What a real `suppress-bounce` looks like for OUR traffic**, with our
   sender and our variables attached.
3. **Confirmation that the dashboard's "Permanent Fail" subscription
   actually delivers the `failed`/`permanent` events** — the naming
   mismatch in §3 makes that worth verifying rather than assuming.

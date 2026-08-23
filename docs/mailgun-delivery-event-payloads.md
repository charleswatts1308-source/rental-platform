# Mailgun delivery-event payloads — observed reference

Captured 2026-08-23 on renters.rent via the temporary capture endpoint
(Release 2, `feature/delivery-capture`). **These are Mailgun's own test
payloads**, fired from the dashboard's "Test webhook" button — synthetic
values (`alice@example.com`, `my_var_1`), but the *structure* is
Mailgun's real one.

This file exists so the #25 receiver is built against observed bytes
rather than documentation. Written up from the capture log before it was
deleted from the box.

**UPDATED 2026-08-23 20:20 — the three real sends are DONE.** Their
observed values are in §7, and they supersede the synthetic ones
wherever the two differ. The headline: **the correlation key works** —
every one of the three came back carrying
`user-variables.case_message_id`.

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

## 7. THE THREE REAL SENDS — observed 23 Aug 2026 on production

All three from real cases on renters.rent, `cases@mg.renters.rent`,
`tags: ["production"]`.

| send | recipient | event | severity | reason | code | case_message_id |
|---|---|---|---|---|---|---|
| 1 | non-existent domain | `failed` | `permanent` | `generic` | 498 | 18 |
| 2 | suppressed address | `failed` | `permanent` | `suppress-bounce` | 605 | 19 |
| 3 | real inbox (Outlook) | `delivered` | — | — | 250 | 20 |

### THE CORRELATION KEY WORKS

Every one of the three carried `"user-variables":{"case_message_id":N}`.
So `CaseNotice::headers()` → `X-Mailgun-Variables` survives Symfony's
Mailgun transport and comes back on the event. The receiver can bind an
event to its `case_messages` row directly, with no recipient-and-timestamp
guesswork. **This was the last real unknown and it is answered.**

### How to tell the three apart

The discriminator is **`reason`**, not `severity`. Sends 1 and 2 are both
`failed`/`permanent`:

- **Send 1, a real bounce attempted:** `reason: generic`, code 498,
  `"No MX for … no such host"`, `session-seconds: 0.024`,
  `bounce-type: "soft"`, and the envelope HAS a `sending-ip`.
- **Send 2, dropped before any attempt:** `reason: suppress-bounce`,
  code 605, `"Not delivering to previously bounced address"`,
  `session-seconds: 0.005`, **no `bounce-type`**, and the envelope has
  **no `sending-ip`** — nothing was ever transmitted.
- **Send 3, delivered:** code 250, `session-seconds: 4.342`, plus
  `mx-host`, `mx-host-ip`, `tls: true`, `certificate-verified: true`,
  `recipient-provider: "Outlook US"`, and
  `primary-dkim: "s1._domainkey.mg.renters.rent"`.

The absent `sending-ip` on send 2 is a second, independent signal that
no delivery was attempted — useful as a cross-check.

### A trap in send 1

`severity: permanent` but `bounce-type: "soft"`. A domain that does not
resolve is treated as permanent for the message, yet Mailgun did NOT add
the address to the suppression list. So **do not assume a permanent
failure suppresses the address.** Send 2 had to use a genuinely
suppressed address (one of the two July hard bounces) to produce the
`suppress-bounce` shape at all.

### Fields present on real traffic but absent from the test payloads

`api-key-id`, `primary-dkim`, `recipient-provider`,
`delivery-status.first-delivery-attempt-seconds`,
`delivery-status.bounce-type`, `flags.is-big`.

Build the receiver defensively: treat every one of these as optional.
The synthetic payloads and the real ones differ in both directions.

### The real cases raised

9RKDKC (send 1), 3YHRKZ (send 2), CZPUAD (send 3). Real cases on
production — abandon them once the capture work is finished.

---

## Everything the run was for is now answered

1. ~~Signature shape~~ — **nested**, confirmed (§1)
2. ~~Content type~~ — **JSON**, not form-encoded (§2)
3. ~~Event naming~~ — no `permanent_fail`; branch on `severity` (§3)
4. ~~Suppressed-address shape~~ — `suppress-bounce`/605 (§4, §7)
5. ~~Does our correlation key survive?~~ — **yes** (§7)

Nothing outstanding requires the capture endpoint. It can come off.

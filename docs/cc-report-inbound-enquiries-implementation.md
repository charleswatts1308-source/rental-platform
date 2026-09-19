# Implementation report — inbound enquiries, stage 1

**Branch:** `feature/inbound-enquiries` (tag `pre-inbound-enquiries` on main).
**Commit:** `b6e3f83`. **Brief:** `docs/cc-brief-inbound-enquiries.md`, accepted
19 Sep 2026. **Suite: 873 passed** (861 before, 12 added). **Not merged. Not
deployed.**

---

## What was built

`MailgunInboundController` now classifies the recipient before acting:

| Recipient | Goes to | Changed? |
|---|---|---|
| 20 alphanumeric chars before `@` | `HandleInboundReply` | No — untouched |
| a configured local part on the inbound domain | `ForwardInboundEnquiry` | New |
| anything else | logged, dropped | No — as before |

**New files:** `config/enquiries.php`, `app/Support/InboundRecipient.php`,
`app/Mail/InboundEnquiryForward.php`, `app/Actions/ForwardInboundEnquiry.php`,
`tests/Feature/InboundEnquiries/ForwardInboundEnquiryTest.php`.
**Modified:** `MailgunInboundController`, `.env.example`.
**No migration** — so no MariaDB check is due under the Migrations rule.

## Deltas from the brief

Five, none of which change the agreed shape. Listed because the rule is that
deltas go in the report, not because any of them is contentious.

1. **`MAIL_ENQUIRY_LOCAL_PARTS` added.** The brief said the addresses would be
   config rather than literals; it did not name a key. Comma-separated, with the
   three agreed local parts as the production default, per the CLAUDE.md
   convention that Mailgun config carries production defaults.
2. **The token regex now exists twice** — canonically in
   `HandleInboundReply::extractToken`, and in `InboundRecipient::isCaseToken`
   for the dispatcher. The brief required `HandleInboundReply` not be modified,
   and dispatch has to know the shape to test it first. Both copies are marked
   in the docblock as needing to change together. **The regression test — a real
   reply still binding to its case — is what catches a drift.** Folding them
   into one helper is a fair later tidy-up; it would have meant editing the
   evidential path, which the brief ruled out.
3. **Forwarded HTML is sanitised through Purifier**, as inbound case bodies
   already are. Not in the brief. These addresses are published and will attract
   hostile mail; passing raw HTML into a mailbox unexamined is not worth the
   saving.
4. **The prefix block carries more than the brief listed** — SPF and DKIM
   results, and Mailgun's spam flag when set, alongside the address, sender and
   time. All were already in the payload. When a landlord's enquiry looks
   doubtful, those three lines are what tell you whether to trust it.
5. **`execute()` takes an optional `CarbonInterface $now`.** Not required by the
   Time rule, since this is not sweep, clock or decision code, but it keeps the
   shape consistent and makes the timestamp assertable.

## Tests — 12 added, none weakened

Each configured address forwards to the configured mailbox (three cases); From
is the enquiry address and Reply-To the original sender; the subject survives
and the arrival address appears in the body; an enquiry writes **no**
`case_messages` row; a missing forward address sends nothing; a destination on
the inbound domain is refused; an enquiry local part on **another** domain is
ignored; an unrecognised local part sends nothing; a plain-text-only body still
forwards; an attachment is declared in the body.

The existing `WebhookInboundReplyTest` — 16 tests including the "malformed
recipient stores no message" case — passes unchanged. That file is the
regression evidence for the evidential path.

## What the suite does NOT prove

A webhook that passes tests and drops real mail is the exact failure this work
exists to remove, and `Mail::fake()` cannot tell you whether Outlook accepted
the message. **Acceptance is the live exercise in the brief**, on production,
because case-reply handling exists nowhere else — the sandbox is outbound only
and can never receive.

Unproven until then: that Mailgun accepts a send whose From is an address no
mailbox exists for; that Microsoft does not junk it (#36 records that shared
pools bite hardest there — **check Junk, not only the Inbox**); and that a real
case reply still binds after the dispatcher change.

## Before merge

1. Run the four-part live exercise in the brief.
2. `MAIL_ENQUIRY_FORWARD_TO` is already set on production; it needs
   `php artisan config:cache` to take effect.
3. `--no-ff` merge once green and acceptance met, then the ledger entry.

## After merge, in order

1. Repoint `privacy.blade.php:60` and `cookies.blade.php:85` to
   `privacy@mg.renters.rent`. **Not before** — until this ships, those addresses
   are dropped, and publishing one sooner would be the same fault in a new
   place.
2. Write #62's letter-1 sentence, through the admin template editor on each box,
   never by raw SQL — the editor is what writes `letter_text_change_history`.
3. #48's remaining half: move the admin login to Charlie's own address, and fix
   the two dev commands that seed `admin@renters.rent`.

## Noticed, not fixed

`.env.example:78` carries `MAILGUN_INBOUND_DOMAIN=inbox.renters.rent` in a
comment — the same known-bad value snag #31 records in `deploy-checklist.md`.
A fresh box set up from that comment would have inbound mail pointed at a
domain with no MX. Out of scope here; worth folding into #31 rather than
leaving in two places.

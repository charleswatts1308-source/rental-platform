# Brief — inbound enquiries (non-case mail on mg.renters.rent)

**Raised:** 19 Sep 2026. **Snags:** #62 (ruled 19 Sep), #48, #30 (related, not touched).
**Design doc:** silent on a landlord enquiry channel. This brief proposes the
first behaviour; if it stands, it earns a D-section. The design doc wins over
this brief wherever they meet.

**Deliverable 0 is this document. No code until it is accepted.**

---

## Why this exists

#62 was ruled on 19 Sep: letter 1 gains a sentence telling the landlord how to
make a separate enquiry, and asking that such enquiries stay out of the repair
thread. No link — a bare address in plain text has the best chance of avoiding
spam treatment, and letter 1 is the one email in the system that must land.

That sentence needs a destination. There is none today: `admin@renters.rent`
has received nothing since 4 Jul (no apex MX; Mailgun suppresses it as
unrouteable), which is #48.

The obvious fix — a second Mailgun route forwarding a new address to Charlie's
mailbox — **is not available. The free tier allows ONE inbound route**, and it
is already spent on the case-reply catch-all. Confirmed by Charlie, 19 Sep.
Upgrading was ruled out on 12 Sep (#36): Mailgun support confirmed paid tiers
do not get better IP pools, so paying buys volume and features, not
deliverability, and nothing here is about volume.

**So the discrimination moves into the application.** This is not a consolation
prize. It is the better design, chosen deliberately: mail to an enquiry address
already reaches our webhook, and handling it in code means stage 2 can make it
a *record* rather than something that exists only in Charlie's Outlook. On a
platform whose proposition is that correspondence does not get lost, that is
the consistent answer.

## What happens today

Mailgun's single route:

```
match_recipient(".*@mg.renters.rent")
forward("https://renters.rent/webhooks/mailgun/inbound")
store()
stop()
priority 0   (temporarily 10 during the 19 Sep investigation — restore to 0)
```

Everything on the subdomain posts to `MailgunInboundController`, which verifies
the Mailgun signature (middleware) and hands the payload to
`HandleInboundReply`. That action calls `extractToken()`, which requires
**exactly 20 alphanumeric characters before the `@`**. Anything else is logged
as `malformed recipient` and dropped; the controller returns 200 either way, on
purpose, so token validity cannot be probed.

**Consequence worth stating plainly: mail to `landlord-enquiries@mg.renters.rent`
already arrives at the application and is already binned.** No new plumbing is
needed to receive it — only to recognise it.

---

## Stage 1 — forward only (this brief)

The smallest thing that gives #62 a working destination. No schema change, no
UI, no new table.

### Scope

1. **A recipient dispatcher in front of the existing action.** The controller
   stops calling `HandleInboundReply` unconditionally. It asks, in this order:
   - 20-char token local part → `HandleInboundReply`, **unchanged in every
     respect**;
   - local part in the recognised-enquiry list → the new enquiry path;
   - anything else → logged and dropped, exactly as today.

   The token test stays first and stays exactly as it is. Case replies are the
   evidential path and this work must be invisible to them.

2. **Recognised enquiry addresses** (Charlie, 19 Sep): `landlord-enquiries@`,
   `privacy@`, `info@` — local parts only, on the inbound domain. Held as
   config, not literals, so a fourth costs an env change and not a deploy.

3. **The enquiry path re-sends the message to Charlie's mailbox** through
   Mailgun:
   - **From:** the enquiry address it arrived at, NOT the original sender. This
     keeps SPF/DKIM aligned to a domain Mailgun signs. A plain forward that
     preserved the sender's From would break their SPF and invite spam
     foldering — the failure mode a raw Mailgun route would have risked.
   - **Reply-To: the original sender.** So hitting Reply in any client answers
     the landlord. This is what makes the channel usable before the Thunderbird
     send-as identity exists.
   - **Subject:** preserved, unmodified.
   - **Body:** preserved. Prefixed with a short block naming which address it
     arrived at, who it came from, and when — because three addresses forward
     to one mailbox and the To: line is the only way to tell them apart.

4. **Destination is config only.** `MAIL_ENQUIRY_FORWARD_TO`, read through
   `config/`, set in `.env` on production. **No default, and never in the
   repo** — it is a private mailbox. If unset: log a warning, drop, return 200.
   Silence beats a crash in a webhook, and the log says why.

5. **Loop guard, non-negotiable.** Refuse to forward to any address on the
   inbound domain. A destination of `anything@mg.renters.rent` would post
   straight back to this webhook and run until Mailgun's limits stopped it.
   Cheap check, catastrophic omission.

### Hard constraints

- **An enquiry MUST NOT write a `case_messages` row.** The escalation counter
  is derived from outbound system rows with a non-null `stage_at_send`, never
  stored and never reset. A stray row inflates every ladder it touches. Same
  invariant the tenant notices already respect (CLAUDE.md, Evidential
  invariants). A test asserts the row count is unchanged across an enquiry.
- **`HandleInboundReply` is not modified.** Dispatch happens in front of it.
- Always 200, on every path, including failures.
- Time stays injected wherever it is read.

### Deliberately out of scope

- **Attachments are not forwarded** in stage 1; the prefix block says whether
  the original carried any, so nothing is silently lost. Landlord photos on an
  enquiry are a stage 2 question.
- **Spam is forwarded as-is.** Three published addresses will attract it.
  Mailgun's spam flags are in the payload and can gate stage 2; for stage 1,
  Charlie's own mail filters are the answer, and the volume is the evidence for
  whether more is needed.
- **No record is kept.** Stage 1 enquiries live only in Outlook. That is the
  accepted cost of shipping the destination this week, and the whole reason
  stage 2 exists.
- **Sending as the enquiry address** (Thunderbird identity + Mailgun SMTP
  credential) is Charlie's client setup, not application work.

### Tests

- Token-shaped recipient still reaches `HandleInboundReply` and still binds the
  reply to the case — the regression that matters.
- Each of the three enquiry local parts sends exactly one mail to the configured
  address, with From = the enquiry address and Reply-To = the original sender.
- An enquiry creates **no** `case_messages` row and **no** case event.
- Unrecognised local part: nothing sent, nothing written, 200 returned.
- Config unset: nothing sent, warning logged, 200 returned.
- Destination on the inbound domain: refused, logged, nothing sent.

### Acceptance — what proves it works

Application tests are not the proof here; a webhook that passes tests and drops
real mail is the exact failure being guarded against. Acceptance is a live
exercise, on production, because case-reply handling exists nowhere else —
staging is the Mailgun sandbox, outbound only, and can never receive. Known and
accepted; not to be worked around with a synthetic-signature path.

1. Send to `landlord-enquiries@mg.renters.rent` from an outside account that is
   **not** the forwarding destination. It lands in Charlie's mailbox, shows the
   prefix block, and Reply goes back to the outside account.
2. Same for `privacy@` and `info@`.
3. **The regression:** raise a real case on production, let letter 1 go, reply
   to it from the outside account. The reply binds to the case as it does today.
   If it appears in Charlie's mailbox instead, dispatch is wrong.
4. Mailgun Logs show all four matching the one catch-all route, as expected —
   the route is not changing.

Then the ledger entry, per the deployment rule.

---

## Stage 2 — make it a record (sketch, not in scope)

New table (working name `inbound_enquiries`), one row per enquiry: which address
it arrived at, sender, subject, body raw + sanitised, received_at, spam flags,
read/handled state. Admin list to read them. Notification to Charlie on arrival.
Attachments decided. Reply-from-the-app is a later question again — replying
from Thunderbird is adequate and needs no build.

Naming note: Charlie said "new landlord enquiries table", but the same table
takes `privacy@` and `info@`, which are not landlord mail. `inbound_enquiries`
with a channel column avoids a misnomer that would mislead for years.

---

## DNS, checked 19 Sep — nothing to change

Stage 1 needs **no DNS record, no Plesk change and no Mailgun route change.**
The three enquiry addresses already arrive: `mg.renters.rent` has MX to
`mxa`/`mxb.eu.mailgun.org`, the catch-all matches every local part on it, and
the payload already reaches this webhook. Only recognition is missing. The
forward is sent through the existing Mailgun sending setup, so SPF, DKIM and
DMARC are untouched.

Two findings from the same check, recorded against #48 in full:

- **The apex now has MX** (`smtp01`/`mail.hostinguk.net`), added around 1 Aug
  per zone serial `2026080104` — a month after the 4 Jul bounce that diagnosed
  "no apex MX". The diagnosis was right when written; the world moved.
- **There is no mailbox behind it.** Tested 19 Sep: a clean "Address not found"
  bounce. Better than July's silent loss, still a dead published contact.

**Why the enquiry addresses stay on `mg.` and do not move to the apex**, even
though the apex would read better in letter 1: apex mail goes to Hostinguk, not
to Mailgun, so it would never reach this application. That makes stage 2
impossible rather than deferred — no record, no admin list, no trail. #62 exists
precisely because correspondence was landing where only one person could see it.
The cosmetic cost of "mg." is the price of keeping enquiries inside the system.

## Open, for Charlie

- **Restore the catch-all route's priority to 0** (changed to 10 on 19 Sep while
  investigating; harmless with one route, but the docs should match the panel).
- **Was a second route created before the cap refused it?** If so, delete it.
- ~~`MAIL_ENQUIRY_FORWARD_TO` needs setting on production before the live test.~~
  **Set on production 19 Sep** (an Outlook mailbox; the value stays in `.env`
  and out of this repo). Needs `php artisan config:cache` to take effect.
- **Charlie intends to create a dedicated mailbox for this later.** By design
  that is a ONE-LINE change — the env value plus a `config:cache`. Nothing else
  in the system knows where the forward lands. Two notes for when he does:
  pick a provider that can **send as** the renters.rent address, which solves
  the reply-identity problem without a second mail client at all; and the
  account address stays private either way, since what landlords see is
  `landlord-enquiries@mg.renters.rent`.
- **Deliverability watch, live test:** the destination is Outlook, and #36
  records that Mailgun's shared pools bite hardest at Microsoft. Check the Junk
  folder during acceptance, not just the Inbox. If it lands there it is a
  safe-senders rule at Charlie's end, not a design fault — but it must be
  discovered during the test rather than when enquiries quietly stop arriving.
- **Reading is safe; replying leaks.** Reply-To is the original sender, so a
  reply from Outlook does reach the landlord — from Charlie's private address.
  The Thunderbird identity (or a send-as capable mailbox) is what fixes that.
  The FIRST enquiry is when this catches you out, because the instinct is to
  hit Reply.

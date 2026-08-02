# Snagging list — plain-English summary

**Written:** 2026-08-02. A business-language view of `llcs-snagging-list.txt`.
Same items, no technical detail. The full list stays the working record.

---

## Serious — these affect the product's core promise

**We cannot tell whether a letter actually arrived** *(#25)*
A bounced letter and an ignored letter look identical to us. The system can
claim a landlord stayed silent when they were never reached.

**Photo uploads reject ordinary phone photos, and can wipe a tenant's work** *(#40)*
Typical phone photos are too big and fail with a message nobody can act on.
Attaching several at once should discard the whole form, losing what they typed.

**The hosting control panel shows settings it is not applying** *(#41)*
Values we set are displayed as saved but never take effect. Anything configured
there is unverified until the host explains it. Ticket raised.

---

## Gaps — things the product cannot yet do

**A mistyped landlord email cannot be corrected** *(#24)*
Once the first letter has gone, the tenant must abandon the case and start again.

**Tenants cannot attach photos to replies** *(#19)*
Only the very first letter can carry evidence. If the problem worsens, there is
no way to show it.

**Contact Us is not a conversation** *(#30)*
A message in, one reply out. The customer cannot respond, and we hold no thread.

**Repair categories cannot be edited** *(#22)*
Adding or retiring a category needs direct database access.

**A known landlord's details do not fill themselves in** *(#7)*
The tenant retypes the name, and a mismatch is silently ignored rather than queried.

**The landlord's email is not shown on the case page** *(#2)*
The tenant cannot see who the letters are going to.

---

## Rough edges — visible to users, cheap to fix

**Town names print in lower case on formal letters** *(#38)*
A letter citing housing law reads as unproofed, which undermines how seriously
it is taken.

**The preview does not show attached photos** *(#39)*
The tenant's only check before sending does not mention the one thing they
cannot take back.

**The welcome message after registering has never appeared** *(#37)*
Shipped on 1 August, failed on the first real sign-up.

**Verification links opened in a different browser force a login first** *(#27)*
Common when email opens in a different browser from the one used to register.
Recently started failing harder than a login prompt.

**One email type sends from the wrong address** *(#32)*
Recipients see an odd "on behalf of" note, which reads as slightly untrustworthy.

**The "Members Only" notice is off-putting** *(#29)*
Shown to people who have done nothing wrong, including on the verification path.

**A menu is still named after a feature we removed** *(#1)*

---

## Internal — no customer sees these

**Written rules and documents that contradict the system** *(#31, #34)*
A rebuild following our own deployment checklist would silently break landlord
replies.

**Some tests can pass without checking anything** *(#26)*
False confidence: they look green whether or not the feature works.

**A latent database trap that could quietly alter stored dates** *(#18)*
Dormant today, but stored dates are evidence.

**A deployment tool crashes on a partly-updated database** *(#17)*

**A temporary DNS setting with nothing recording that it is temporary** *(#33)*

**Development email sends from a personal outside address** *(#35)*

**Demo data reads oddly; an internal display shows the wrong countdown** *(#12, #13)*

**Login forms always start empty, slowing repeated testing** *(#28)*

---

## Closed recently

**Exposed mail credentials — rotated and proven** *(#23)*

**Whether to pay Mailgun more — answered, no** *(#36)*
Paying buys volume, not better delivery. No upgrade, no dedicated address.

**Delivery visibility duplicate** *(#8)* — retired into #25.

---

## One thing to reconcile

Six items *(#4, #14, #15, #16, #20, #21)* are recorded as resolved in the session
notes but still read as open in the full list. Worth settling so the count is
honest.

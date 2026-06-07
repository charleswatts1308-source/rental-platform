# Design doc addendum — D13: letter consent model (Sun 2026-06-07)

Follow-up edit to `docs/llcs-silence-model-design.md`, supplementing the
Phase 3 design update of the same date. Apply alongside it.

---

## 1. ADD new decision D13 (after D12):

### D13 — Letter consent: authorise once, preview the first, notify the rest

Letters go out in the tenant's name ("Yours faithfully, {{tenant_name}}").
The consent model:

1. **Authorisation is given once, at case creation.** Explicit wording at
   the create-case form: by opening this case the tenant authorises
   renters.rent to send escalating letters in their name if the landlord
   does not respond. The letter wording is viewable at that point (the
   Phase 1 renderer against the case's actual data).
2. **The first letter is previewed before it is sent.** Case creation is
   the one moment a preview costs nothing — the tenant is present and
   acting. The form flow becomes: enter details → see notice 1 rendered
   with their actual description → confirm → send. This also catches
   description typos before they are frozen into evidence (D9 makes the
   description immutable and ubiquitous, so this is the only correction
   point).
3. **Subsequent letters are notified after, never gated before.** The
   sweep sends; the tenant notification says what went out; the full
   letter sits on the case record. Pause (D10) is the standing opt-out
   for a tenant who wants letters to stop.

Explicitly rejected: per-letter tenant approval ("here's the next
letter, press send"). It would reintroduce the disease the silence
model cured — escalation reliable only up to the tenant's attention —
and recreate D7's judgment step in new clothes. The tenant controls
the process (open, pause, resolve, abandon) and sees everything; they
do not gate each send. The authorisation is the contract; the
templates are the published wording; the case record is complete.

Phase 3 build consequence: the create-case flow gains the preview +
confirm step (today it fires notice 1 immediately with no preview).
The authorisation copy is content (template/data), not code.

---

## 2. §8 Phase 3 outline — APPEND to the Phase 3 entry:

> Create-case flow gains the notice-1 preview + confirm step and the
> one-time authorisation wording per D13.

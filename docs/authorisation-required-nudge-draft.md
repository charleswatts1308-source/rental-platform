# authorisation_required_nudge — draft

Tenant-facing prompt. Fires when an engaged landlord has gone quiet and
the next notice is prepared and waiting on the tenant to send it.
NOT legal text — this is a prompt/reassurance row, my eyes only.

---

**Subject:** Your landlord hasn't replied — ready to send the next notice?

**Body:**

Your landlord hasn't responded on [case reference] since their last
reply on [date].

The next notice is prepared and ready. Because your landlord has
engaged with this case before, we don't send further notices
automatically — it's your call whether to send the next one now.

[ Review and send the next notice → ]

If your issue has since been resolved, you can leave this here — we
won't send anything unless you choose to.

---

## Notes on the choices

- **"ready to send the next notice?"** — frames it as the tenant's
  decision, not a demand. Matches the "your will governs the engaged
  class" principle.
- **"Because your landlord has engaged with this case before"** —
  states *why* this one needs a click when the never-engaged path
  doesn't. The tenant who's had both kinds of case shouldn't be
  confused by the difference.
- **"If your issue has since been resolved, you can leave this here"**
  — the safe exit, explicit. This is what catches the satisfied tenant
  and stops them feeling they must act. ("So do we" made visible.)
- **No deadline, no countdown, no "or your case will be closed"** —
  deliberately. A deadline would turn a prompt into pressure and load
  the burden back onto the tenant. The dormancy tail handles inaction
  quietly; the tenant doesn't need to be threatened with it.

## One open question for you

Should this nudge mention, even lightly, that the case will eventually
go quiet (dormant) if they do nothing? Arguments both ways:

- **For:** honesty — the tenant should know inaction has a consequence.
- **Against:** it reintroduces the pressure the copy is trying to
  avoid, and "we won't send anything unless you choose to" already
  tells them inaction is safe. The dormancy tail is recoverable (D11),
  so the consequence is mild.

My lean: leave it out of the nudge. If we want the tenant to know about
dormancy, that belongs on the case page as status, not in a prompt
designed to be low-pressure. But your call.

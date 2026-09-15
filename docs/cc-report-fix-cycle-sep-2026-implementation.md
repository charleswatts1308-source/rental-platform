# Fix cycle, September 2026 — implementation report

**Branch:** `feature/fix-cycle-sep-2026`
**Forked from:** `main` at `7fdebb4`, tagged `pre-fix-cycle-sep-2026`
**Commits:** 43
**Suite:** 803 green at fork → **859 green** at report
**Migrations:** none — this branch adds no schema change of any kind
**State:** not merged, not pushed since 13 Sep, not deployed anywhere

This report closes the phase per CLAUDE.md. It records what changed, what
a human actually walked, where tests were altered and why, and what is
deliberately still open. It is written to be read by whoever picks the
branch up, on the assumption they were not here.

---

## 1. What this cycle turned out to be

It was planned as six built-but-unwalked fixes needing a browser walk.
It became a walk-and-fix session: Charlie tested on dev, reported what he
saw, and each finding was diagnosed and fixed in the same turn, then
retested immediately. That mode is recorded as his standing preference.

The arithmetic is the argument for it. Six fixes came into the cycle;
**ten new snags (#64–#73) were raised by walking**, of which eight were
fixed the same day. Three of those — #71, #72 and #73 — were defects that
no test would have found and that had been sitting in shipped or
about-to-ship code.

---

## 2. Snags closed

| # | What it was | Walked by Charlie |
|---|---|---|
| **#2** | Landlord email absent from the case page; panel titled "Recipient" rather than by role | yes |
| **#27** | Verification link demanded a login in a second browser, or 403'd | yes |
| **#40** | Per-file cap too small; no combined-size rule | already fixed, never closed |
| **#44** | "Photo saved" cue followed an abandoned draft into a new case | already fixed, never closed |
| **#50** | Severity a required field that reached nobody | yes |
| **#51** | Postcode never checked to exist; town never reconciled | yes |
| **#53** | Remove on one staged photo removed them all | yes, at ceiling 3 |
| **#54** | Attachment coverage gap above ceiling 1 | yes — see §5 |
| **#57** | No error pages at all | yes, all four |
| **#58** | Photo check per-file only, never summed | yes |
| **#61** | Retaliation sentence in the letter footer | prod templates, yes |
| **#64** | No show/hide on any password field | yes |
| **#65** | Same-browser verification gave no confirmation | yes |
| **#66** | Landlord asked for on the case form, not the property | yes |
| **#67** | Register button needed two clicks | yes |
| **#68** | Form stated only one of three photo limits | yes |
| **#69** | A reply sent with no preview, where letter 1 has one | yes |
| **#70** | Case page sidebar too narrow for the reply form | yes |
| **#71** | Double-click on Send posted the letter twice | yes |
| **#72** | New photos wiped the staged set instead of adding | yes |
| **#73** | Replies shared letter 1's attachment ceiling | yes |

Also built, not a snag: **#19**, attachments on tenant replies — open
since the June live-fire.

---

## 3. The three that matter most

Everything else is a fix. These three are worth reading before touching
this area again.

### #71 — the double-click

A double-click on Send wrote **two** evidential rows and posted two
letters. Found by accident. Outbound rows are the evidence record, so a
duplicate is not cosmetic.

The blast radius was established before fixing, and one branch of it was
worse than the symptom: **escalation authorise was not guarded**, and a
duplicate escalation letter advances the ladder, whose counter is derived
from these rows and never resets (D3). There is no way back from that.
Create-case confirm turned out to be safe already — but by accident, not
design: the draft is pulled from the session on first confirm.

Two layers, and the distinction is deliberate. The browser disables the
submit button (comfort, re-enables after ten seconds so a stalled
connection cannot strand a tenant). The server consumes a one-time token
minted at render (guarantee, because two tabs or a back-button resubmit
never touch the script).

Two judgement calls inside it, both arguable and both recorded in the
snag: a request carrying **no** token is allowed through, because losing
a genuine message is worse than an occasional duplicate; and a refused
duplicate returns the **success** message, because the tenant pressed
send once and their reply did go.

### #72 — photos wiped by choosing a replacement

Remove one of three staged photos, pick a replacement, and the two
survivors vanished. The letter would have carried one photo where the
tenant believed it carried three.

**This was a deliberate rule with a test pinning it** — a test literally
named "#46 — newly chosen photos REPLACE the staged set rather than
adding to it". It was correct when the ceiling was 1, where replace and
add are the same thing, and became wrong the moment the ceiling rose.
The test was **inverted**, not deleted, and says why.

This is also the first defect that #54's coverage gap actually hid, which
is the retrospective justification for #54 having existed at all.

Both halves had to change together — the server's resolver and the
browser's picker. Had only one changed, the screen would show a set the
server would not send.

### #73 — one ceiling doing two jobs

Surfaced through a **sentence**. Charlie asked for the ceiling-0 message
to say photos become possible once the landlord replies. Checking whether
that was true found that #19 (built the same afternoon) had reused letter
1's ceiling for replies, so switching photos off for letter 1 switched
them off everywhere.

`docs/attachment-policy-design.md` has listed the two as separately
configurable since 9 August. CLAUDE.md makes the design doc authoritative,
so this was a divergence to fix regardless of the wording.

The reasoning is the design's own, and Charlie reached it from the wording
without knowing the doc said it: a ceiling of 0 exists on deliverability
grounds, and that risk is a **cold** letter to a stranger. Once the
landlord has written back it has largely gone.

---

## 4. Test deltas

CLAUDE.md requires these listed rather than buried. **No assertion was
weakened.** Four categories:

**Inverted — one.** `#46 — newly chosen photos REPLACE the staged set`
became `#72 — newly chosen photos ADD to the staged set`. The old
expectation was right for its time and wrong once the ceiling rose above
one. Two further tests were added alongside it (remove-then-replace, and
the whole-set ceiling).

**Repointed — sixteen plus four.** Sixteen tests posted straight at
`cases.reply`, which #69 made the confirm step only. They now drive both
steps through a shared `sendTenantReply()` helper in `tests/Pest.php`;
each asserts exactly what it asserted before, and now exercises the #71
token on the real path as a side effect. Four redirect assertions
(`DashboardOnboardingTest`, `PropertyCreateTest`) were repointed for #66 —
each still pins an exact destination.

The `forbids reply from …` tests were **deliberately left** posting
directly at `cases.reply`: they assert the send endpoint itself is gated,
which is worth keeping pointed at the send endpoint rather than at the
preview in front of it.

**Tightened — two.** The `?verified=1` redirect assertion became an
assertion on the flashed message *and* the destination (#65). A per-file
size assertion that matched bare text now matches it inside its `<strong>`
(#68).

**Label changes — three.** `Send reply` → `Preview reply`, and
`Send the first notice` → `Preview the first notice`, asserted as a pair
so the two cannot drift apart.

---

## 5. What was walked, and what "closed" means for #54

Charlie walked, on dev at ceiling 3: registration and verification in both
browsers; property → landlord → case; the postcode lookup; severity gone;
photo removal; the selection total; the stated limits; all four error
pages; the reply flow end to end with three attachments; the double-click;
add/remove/add on both forms; the two ceilings.

**#54 is closed on judgement, and its entry says so.** The permutation
space is not exhausted and never will be — that was always the entry's
point. It is closed because the centre is proven and the risk no longer
lives at the edges. Anyone reopening this area should read #54 before
assuming it was signed off as exhaustive.

**Everything on this branch has now been walked.** #64 and #69 were the
last two, confirmed after this report was first written.

---

## 6. Deliberately not done

- **The confirmation dialogs** on Mark resolved and Abandon still use the
  browser's native `confirm()`. Raised, discussed, left alone: replacing
  them means building a dialog, and it is polish rather than defect.
- **The asymmetry** between Mark resolved (a button) and Abandon (an
  expander) was questioned and deliberately kept. Abandon carries a reason
  field and needs the room; resolve is the common, desirable outcome and
  should be one press.
- **#62** — whether landlords ever get a written channel that is not a
  case reply. Untouched, still the open question behind #48.
- **#63** — the tenant's name rendering as stored on a formal notice.
  Untouched.
- **#1** — the nav restructure. Deferred 12 Sep into the content change.

---

## 7. Before this merges

1. **Nothing outstanding to walk.** Every fix and feature on this branch
   has been exercised in a browser by Charlie on dev.
2. **No MariaDB check needed.** The Migrations rule in CLAUDE.md is not
   triggered: this branch creates and alters nothing. Worth stating
   explicitly so nobody goes looking.
3. **Merge `--no-ff`**, tag, per the Git rule.

## 8. On deploy — things that will not happen by themselves

- **`attachments.reply_max` needs seeding** on gafol and prod. Until it
  exists, `PhotoLimits::replyCeiling()` quietly follows letter 1 —
  correct, and not what an admin editing the field would expect.
- **The retaliation sentence (#61) is still in the database** on gafol and
  on the dev box. Prod was done by hand through the template editor. The
  seeder change only reaches a fresh install, deliberately: a seed
  migration once nearly reverted hand-edited templates.
  `exhaustion_landlord_closer` carries it as well as
  `landlord_wakeup_generic` — check both.
- **`migrate:status` on both boxes**, still not re-run since the 12 Sep
  deploy. No migration shipped then or now, so no drift is expected, but
  the reconciliation rule asks for it.
- **Ledger last**, per CLAUDE.md.

## 9. Unrelated, and time-bound

Prod case **`BBY6GV`** escalates to letter 2 on **26 September 2026** and
will send real mail to a real address. It is a test case from the #25
control send and wants abandoning before then. Nothing on this branch
affects it; it is recorded here because it has an actual date on it.

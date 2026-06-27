# Claude Code automation layers — a plain-English orientation

Filed 2026-06-27. A read-at-leisure explainer, not a build spec. The
question behind it: "Am I a clumsy human interface carrying messages
between Claude-chat and Claude Code, and should I automate that?"

The honest answer: partly yes, partly no — and the distinction is the
whole point. Read slowly. Nothing here needs doing now.

---

## The shape of the problem

Right now there are three parties:

- **You** — steer, decide, rule on judgement calls.
- **Claude (chat)** — architectural review, design, plain-language
  translation. (This file's author.)
- **Claude Code (CC)** — executes: writes code, runs migrations,
  commits.

You are the wire between chat-Claude and CC. When I write "paste this
to CC" and you paste it, then paste CC's reply back — that's you being
the courier. It feels clumsy because some of it IS clumsy, and some of
it is the most valuable thing you do. Separating those two is the
goal.

There are TWO different things travelling down that wire:

1. **Mechanical relay** — "run these three commands," "commit these two
   files." Unambiguous. You add nothing by being in the middle except
   latency and a chance to mistype. THIS is the clumsy part.

2. **Judgement** — "is dotrent's divergence drift or tested state?",
   "does the solicitor gate block the family trial?", "that framing is
   wrong." THIS is not courier work. This is you doing the one job
   neither AI can do — and today alone it caught three errors.

Automation should eat #1 and leave #2 alone. Most of the temptation
("just connect them!") would eat both — which is why the rest of this
doc matters.

---

## The five layers, cheapest first

Claude Code extends through five mechanisms. They are NOT alternatives
competing for the same job — they sit at different points. The useful
rule of thumb: *am I short on knowledge, context, or capability?*
Knowledge → Skill. Context → Subagent. Capability → MCP server. And
when in doubt, build the cheapest artifact that moves the needle — a
markdown file beats a deployed service every time.

Here they are, cheapest and most-relevant-to-you first.

### 1. CLAUDE.md — you already have this

The repo's standing rules. CC reads it every session. Every rule
we've added — the Migrations rule, the new Deployment-ledger rule —
lives here. This is the single most effective mechanism and you've
been using it all along. It's the constitution. Nothing to change;
just naming it so the rest has context.

What it can't do: it's read and *honoured by convention*. CC follows
it because it's told to. It does not *force* anything. That gap is
what the next two layers close.

### 2. Skills — the relay-killer (most relevant to you)

A Skill is a markdown file (`SKILL.md`) describing a repeatable
procedure, that CC can run on command (`/name`) or invoke itself when
the task fits. Think of it as a saved recipe.

Why it's the one to care about: most of the "CC words" I draft for you
are the SAME shapes over and over — "run step 0 per the deploy plan,
write the findings to the ledger." That's a *knowledge* gap (CC needs
to know your routine), and knowledge gaps are exactly what Skills fill.
Turn that routine into a skill, and instead of me drafting a paste-block
you ferry, you type `/deploy-step` and CC already knows the dance.

The relay shrinks. You stop being the courier for routine steps. You're
still fully in the loop for the decisions — the skill runs the
mechanical part, you still rule on what it finds.

Cost: it's a markdown file. The cheapest possible artifact. This is why
it's the first thing worth doing.

### 3. Hooks — the rule-enforcer (relevant to your safety rules)

A Hook is a bit of automation that fires around an event — before a
tool runs, when a session starts, before CC stops. Unlike CLAUDE.md
(which CC *chooses* to honour), a hook *mechanically* fires. It's the
difference between a sign saying "check your migrations" and a gate
that won't open until you have.

Why it matters for you specifically: two of your rules are
load-bearing and currently rest on CC remembering them —

  - "hold the push until I explicitly ask"
  - "manual MariaDB SHOW CREATE TABLE before any migration merge"

As hooks, those stop being convention and become physically enforced.
The push *can't* happen without your word; the merge *can't* happen
without the schema check. The framing people use: this is where your
working agreements start behaving like CI — automated guardrails right
next to the edit loop.

Cost: more than a markdown file (a hook runs actual commands), but
small. Worth it precisely for the rules that would hurt most if missed.

### 4. Subagents — NOT "chat-Claude talking to CC"

This is the one that sounds like the answer to your question and isn't.

A subagent is CC spawning a specialist helper *inside its own session*
— its own context window, its own narrow job (run the tests, review the
diff), reporting back to the main CC. It is not me talking to CC. It's
CC delegating to itself.

The important safety detail: the recommended pattern is to keep
subagents *read-only* and route all the actual writing/committing back
through the parent agent that can ask YOU for approval. So even the
recommended automation deliberately keeps a human-approval chokepoint.
That's not a limit you're failing to escape — it's the designed-in
safety boundary, there for the same reason your corrections today
mattered.

For a solo operator, subagents solve a problem you mostly don't have
yet (parallel specialist work polluting one context). File under
"later, maybe."

### 5. MCP servers / Agent SDK — beyond what you need now

MCP servers connect CC to *external systems it otherwise can't reach* —
a database, GitHub, an issue tracker — as first-class tools. The Agent
SDK is the library for building fully programmatic agents. Both are
real and powerful. Both are more infrastructure than a one-person
project should take on today. Named here only so the map is complete.

---

## What this means for you, concretely

Sized to a solo operator who should NOT be building agent
infrastructure mid-deploy:

- **You're a clumsy interface for the mechanical relay.** True. Fixable
  with Skills (and a couple of Hooks). Worth doing — between phases.
- **You're NOT a clumsy interface for the judgement.** The fussing/
  pushing correction, the dotrent disagreement, the duplicate-sentence
  catch — those are the human-in-the-loop checkpoints the whole system
  is *designed* to preserve. Removing yourself there isn't efficiency,
  it's removing the quality gate.

The one cardinal principle, repeated across everything serious written
on this: keep the setup small enough that you can explain why every
piece exists. You already work this way — you took the 30-line
CLAUDE.md, not the 800-line one.

## The actual first move (when you're ready, not now)

1. Pick your 2–3 most-repeated CC relay sequences. Make each a Skill
   (markdown). Start with the deploy-step / ledger-write routine.
2. Take your two hardest safety rules ("hold the push," "manual
   MariaDB check before migration merge") and make each a Hook.
3. Stop there. Live with it. Add more only when a real friction tells
   you to — never because a checklist exists.

Everything else (subagents, MCP, SDK) waits until a concrete need
names itself.

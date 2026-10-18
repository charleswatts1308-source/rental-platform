# Button colour inventory — 18 Oct 2026

Snapshot taken before any button-colour policy existed. Immutable record;
a policy, when agreed, goes in its own doc.

Theme: Bootswatch **Spacelab 5.3.0** (layouts/app, guest, errors/_shell),
swapped to **Darkly 5.3.0** under prefers-color-scheme: dark. Hex values
below are Spacelab from memory of 5.3.x — verify against the CSS before
relying on them. Inline hexes do NOT change in dark mode.

## Colours in use (live pages and email)

| Colour | Where | Used for |
|---|---|---|
| btn-primary (Spacelab blue ~#446e9b) | ~30 uses: auth forms, case flow, properties, settings, error pages, home/about CTAs | main forward action: submit, save, preview, send, primary CTA |
| btn-outline-primary | admin list View/Edit, "Raise a new case", "See your options", escalation-routes back | lower-priority navigation |
| btn-success (Spacelab green ~#3cb521) | how-it-works "Start a Repair Case"/"Start a Repair Notice"; admin template "Confirm and save" | mixed |
| btn-outline-success | "Mark resolved" | final positive action |
| btn-danger / outline-danger / red btn-link | Delete account; abandon case; remove document; remove staged photo | destructive |
| btn-outline-secondary (grey) | ~20 uses | Back, Cancel, Edit, Pause, row actions |
| btn-secondary (solid grey) | delete-account modal Cancel | cancel |
| btn-link | Log out, Home on error pages, one Cancel | tertiary |
| inline #047857 (emerald) | navbar Register (solid) / Login (outline); contact form Send; admin contact-messages View / Send Reply / Back | bypasses theme |
| inline #2563eb (Tailwind blue-600) | every letter-template email button ("Open this case", "Start a repair case", "Review and send the next notice") | email CTA |
| #2d3748 (Laravel mail theme) | verify-email and password-reset emails | email CTA |

## Inconsistencies found

1. "Start a case" is green on how-it-works, blue on home/about/cases index,
   outline-blue in the case action panel. how-it-works also labels it two
   ways ("Start a Repair Case" / "Start a Repair Notice").
2. Register is green (#047857) in the navbar but blue (btn-primary) as
   "Sign up" on the home page. #047857 is a second green, not Spacelab's.
3. Contact page and admin contact-messages pages use inline #047857 for
   everything, including a solid green "Back" — every other Back is
   outline-secondary.
4. Admin template "Confirm and save" is green; every other save/confirm/
   send is blue.
5. Cancel styled three ways (btn-link, solid secondary, outline-secondary).
6. Destructive actions are red but at three weights. danger-button and
   secondary-button Blade components exist but are unused.
7. Emails use two colours (#2563eb letters, #2d3748 auth mail); neither
   matches the site.
8. Inline hexes ignore dark mode.

Unrouted/archived pages (welcome, welcome-2, welcome-3, content-archive/*)
also use #047857 and btn-success; ignored above as not reachable by users
(content-archive is gated).

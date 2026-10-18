# Button policy

Agreed 18 Oct 2026 (Charlie). Living doc — amend here, not in a new file.
Background and the pre-policy inventory: `button-colour-inventory-2026-10-18.md`.

## The rule

| Colour | Bootstrap class | Use for |
|---|---|---|
| **Logo green** `#047857` | `btn-primary` | The ONE main action on a page: submit, save, send, sign up, start a case. |
| Logo green outline | `btn-outline-primary` | Lower-priority navigation: View/Edit in lists, "Raise a new case", header Login. |
| Grey outline | `btn-outline-secondary` | Back, Cancel, Edit-to-go-back, Pause, secondary row actions. |
| Red | `btn-danger` / `btn-outline-danger` | Anything that deletes or ends something. |
| — | `btn-link` | Tertiary text actions (Log out, Home on error pages). |

**Green `btn-success` is not used for actions** — it read as a second
"main" colour. Exception kept: "Mark resolved" (`btn-outline-success`), a
positive end state.

**Emails:** buttons are inline-styled `background: #047857` (letter
templates) and the Laravel mail theme's `.button-primary` is `#047857`
(verify-email, password reset). Same green as the site.

## Why green, not the theme blue

The logo is green (house `#10b981`, wordmark `#047857`). Spacelab's blue
`#446e9b` competed with it — two unrelated "brand" colours on one page.
Main action = brand colour.

## How it is wired

- `resources/views/partials/theme-overrides.blade.php` recolours
  `btn-primary` / `btn-outline-primary` via Bootstrap 5.3 CSS variables.
  Included by every layout — `layouts/app`, `layouts/guest`,
  `errors/_shell` — straight after the Bootswatch stylesheet. **A new
  layout must include it too**, or its buttons fall back to blue.
- Dark mode (Darkly): solid buttons stay `#047857`; outline buttons use
  the brighter house green `#10b981` so they stay visible.
- Darkly contrast fixes (18 Oct 2026, same partial): grey outline buttons
  become light grey `#ced4da`; `alert-light`, `bg-light`, `text-bg-light`
  and `bg-white` surfaces become dark `#303030` with light text. Darkly
  makes these mid-grey `#adb5bd` under white text — near-unreadable.
  Scoped by a `.theme-dark` class that `layouts/app` puts on `<html>` only
  when it swaps to Darkly (guest and error pages never use Darkly).
- **Never inline a colour on a button** (`style="background-color: …"`).
  Use the class. Inline colours ignore dark mode and drift — that is how
  the pre-policy mess happened.

## Not covered

Unrouted pages (`welcome`, `welcome-2`, `welcome-3`) and `content-archive/*`
still carry old inline greens and `btn-success`. Left alone: not reachable
by users. Bring into line if any is ever revived.

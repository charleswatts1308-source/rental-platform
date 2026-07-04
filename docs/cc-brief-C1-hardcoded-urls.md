# CC Brief — C1: find hardcoded dotrent.net / domain literals

## Goal
Confirm no hardcoded `dotrent.net` (or other domain literals) exist in the codebase or
templates, so that after the DNS flip all outbound URLs render from `APP_URL`
(https://renters.rent) and switch automatically. A stray literal would keep pointing at
dotrent.net after the flip — the exact bug C1 guards against.

## Report first — do not change anything yet
This is a search-and-report task. Do NOT edit files. Return findings; we decide fixes after.

## Search

Run these from project root and report the full output of each:

```bash
grep -rn "dotrent" app/ resources/ config/ routes/ database/ --include="*.php" --include="*.blade.php"
grep -rn "dotrent\.net" . --include="*.php" --include="*.blade.php" --include="*.js" --include="*.css" --exclude-dir=vendor --exclude-dir=node_modules
grep -rn "gafol" app/ resources/ config/ routes/ --include="*.php" --include="*.blade.php"
grep -rni "https\?://[a-z0-9.-]*\.\(rent\|net\)" app/ resources/ config/ routes/ --include="*.php" --include="*.blade.php" | grep -v "APP_URL\|config('app.url')\|env("
```

Also flag any place a full URL is built by string concatenation rather than from
`config('app.url')`, `url()`, `route()`, or `APP_URL`:

```bash
grep -rn "http://\|https://" app/ resources/views/ --include="*.php" --include="*.blade.php" | grep -v "schema\|xmlns\|w3.org\|googleapis\|bootstrapcdn\|jsdelivr\|cdnjs"
```

## What to report
For each hit: file, line number, the line, and a one-line note on whether it looks like
(a) a genuine hardcoded domain that would break at flip, (b) a comment/doc string (harmless),
or (c) a legitimate external URL (CDN, schema, etc. — ignore).

## Expected good outcome
The only domain references should come from `APP_URL` / `config('app.url')` / `route()` /
`url()`. No bare `dotrent.net` or `gafol` literals in mail templates, notification classes,
or Blade views. If that's what you find, report "C1 clear."

## Note
The email-facing paths matter most: mailables, notification classes, and any Blade used to
render outbound letters or tenant-dashboard links. Prioritise reporting those.

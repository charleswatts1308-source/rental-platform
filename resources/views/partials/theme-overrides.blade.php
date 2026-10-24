{{-- Theme overrides on top of Bootswatch. Included by every layout (app,
     guest, errors/_shell); must load AFTER the Bootswatch stylesheet.

     1. Button policy (docs/button-policy.md, 18 Oct 2026): primary buttons
        are the logo green #047857, not Spacelab's blue.
     2. Darkly contrast fixes (18 Oct 2026). Only layouts/app swaps to
        Darkly, and it adds .theme-dark to <html> when it does — so these
        apply only when Darkly is really loaded, never on the always-light
        guest/error pages. Darkly turns "light" surfaces mid-grey #adb5bd
        under white text, and outline-secondary #444 on a #222 page: both
        near-unreadable.
     3. Light-mode body text (24 Oct 2026): Spacelab sets body text to a
        mid grey, which read as too faint on the homepage. Bootstrap's
        standard near-black instead. Headings were already dark.
     4. Font (24 Oct 2026): Roboto instead of Spacelab's Open Sans, chosen
        for its one-storey "g" (Charlie preferred the BBC's). The logo SVG
        sets Arial itself and is unaffected. Lead text drops Bootstrap's
        light weight for normal: large-but-light looked thin in Roboto. --}}
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap">
<style>
    :root {
        --bs-font-sans-serif: "Roboto", system-ui, -apple-system, "Segoe UI", "Helvetica Neue", Arial, sans-serif;
        --bs-body-font-family: var(--bs-font-sans-serif);
    }
    .lead { font-weight: 400; }
    html:not(.theme-dark) {
        --bs-body-color: #212529;
        --bs-body-color-rgb: 33, 37, 41;
    }
    .btn.btn-primary {
        --bs-btn-color: #fff;
        --bs-btn-bg: #047857;
        --bs-btn-border-color: #047857;
        --bs-btn-hover-color: #fff;
        --bs-btn-hover-bg: #065f46;
        --bs-btn-hover-border-color: #065f46;
        --bs-btn-active-color: #fff;
        --bs-btn-active-bg: #064e3b;
        --bs-btn-active-border-color: #064e3b;
        --bs-btn-disabled-color: #fff;
        --bs-btn-disabled-bg: #047857;
        --bs-btn-disabled-border-color: #047857;
        --bs-btn-focus-shadow-rgb: 4, 120, 87;
        background-image: none;
        border: 1px solid var(--bs-btn-border-color);
    }
    /* Spacelab styles .btn-primary with a hard-coded blue gradient and blue
       border, and its hover rule (.btn-primary:not(.disabled):hover) is more
       specific than the block above — so hover turned the button BLUE
       (reported on prod, 18 Oct 2026). This out-ranks it. */
    .btn.btn-primary:not(.disabled):hover,
    .btn.btn-primary:not(.disabled):focus,
    .btn.btn-primary:not(.disabled):active {
        background-image: none;
        border: 1px solid var(--bs-btn-hover-border-color);
    }
    .btn.btn-outline-primary {
        --bs-btn-color: #047857;
        --bs-btn-border-color: #047857;
        --bs-btn-hover-color: #fff;
        --bs-btn-hover-bg: #047857;
        --bs-btn-hover-border-color: #047857;
        --bs-btn-active-color: #fff;
        --bs-btn-active-bg: #065f46;
        --bs-btn-active-border-color: #065f46;
        --bs-btn-focus-shadow-rgb: 4, 120, 87;
    }

    /* ---- Darkly only ---- */
    .theme-dark .btn.btn-outline-primary {
        --bs-btn-color: #10b981;
        --bs-btn-border-color: #10b981;
    }
    .theme-dark .btn.btn-outline-secondary {
        --bs-btn-color: #ced4da;
        --bs-btn-border-color: #6c757d;
        --bs-btn-hover-color: #222;
        --bs-btn-hover-bg: #ced4da;
        --bs-btn-hover-border-color: #ced4da;
        --bs-btn-active-color: #222;
        --bs-btn-active-bg: #dee2e6;
        --bs-btn-active-border-color: #dee2e6;
    }
    .theme-dark .alert-light {
        --bs-alert-color: #dee2e6;
        --bs-alert-bg: #303030;
        --bs-alert-border-color: #444;
        background-color: #303030;
        color: #dee2e6;
    }
    .theme-dark .bg-light,
    .theme-dark .text-bg-light,
    .theme-dark .bg-white:not(.header-bar) {
        background-color: #303030 !important;
        color: #dee2e6 !important;
    }
</style>

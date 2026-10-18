{{-- Theme overrides on top of Bootswatch. Included by every layout (app,
     guest, errors/_shell); must load AFTER the Bootswatch stylesheet.

     1. Button policy (docs/button-policy.md, 18 Oct 2026): primary buttons
        are the logo green #047857, not Spacelab's blue.
     2. Darkly contrast fixes (18 Oct 2026). Only layouts/app swaps to
        Darkly, and it adds .theme-dark to <html> when it does — so these
        apply only when Darkly is really loaded, never on the always-light
        guest/error pages. Darkly turns "light" surfaces mid-grey #adb5bd
        under white text, and outline-secondary #444 on a #222 page: both
        near-unreadable. --}}
<style>
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

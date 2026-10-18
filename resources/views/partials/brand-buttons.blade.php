{{-- Button policy (docs/button-policy.md, agreed 18 Oct 2026): primary
     buttons are the logo green (#047857, the "Renters" wordmark), not
     Spacelab's blue. Included by every layout (app, guest, errors/_shell);
     must load AFTER the Bootswatch stylesheet. --}}
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
    @media (prefers-color-scheme: dark) {
        .btn.btn-outline-primary {
            --bs-btn-color: #10b981;
            --bs-btn-border-color: #10b981;
        }
    }
</style>

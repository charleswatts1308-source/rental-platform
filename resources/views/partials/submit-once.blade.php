{{--
    Disable a submit button once its form is on its way — #71.

    Charlie double-clicked Send on a reply (15 Sep 2026): case LYE62E took
    two outbound rows a second apart and the landlord got two letters.

    This is the comfort, not the guarantee. The guarantee is the one-time
    send token the server consumes, because a determined double-submit
    (two tabs, a resubmitted back button, a stalled connection the user
    gives up on) never touches this script at all. Both exist; neither is
    sufficient alone.

    Applies to every POST form on the page. A GET form — search, filters —
    is left alone: re-running one is harmless and disabling it would be an
    irritation.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[method="POST"], form[method="post"]').forEach(function (form) {
        form.addEventListener('submit', function () {
            // Fires only when the form is ACTUALLY submitting: a form the
            // browser has refused on its own validation never gets here,
            // so a required field left blank does not strand the button.
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
                if (button.disabled) {
                    return;
                }

                // Remember the label before replacing it — a failed
                // submission that comes back to the same DOM must not be
                // left reading "Sending…" forever.
                const original = button.innerHTML;

                button.disabled = true;
                if (button.tagName === 'BUTTON') {
                    button.innerHTML = 'Sending&hellip;';
                }

                // A safety valve. If the submission never completes — the
                // connection stalls, the user cancels — the page is still
                // sitting there, and a permanently dead button would mean
                // a tenant could not send at all. Ten seconds is long
                // enough that it cannot re-enable inside a double-click.
                setTimeout(function () {
                    button.disabled = false;
                    if (button.tagName === 'BUTTON') {
                        button.innerHTML = original;
                    }
                }, 10000);
            });
        });
    });
});
</script>

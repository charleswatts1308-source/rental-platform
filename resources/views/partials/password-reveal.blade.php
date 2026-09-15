{{--
    Show/hide on every password field — snag #64.

    Raised by Charlie 15 Sep 2026: a mistyped password is invisible, so on
    login it cannot be told from a wrong one, and on registration it
    surfaces only as a mismatch error that does not say which of the two
    boxes is wrong.

    Applied from ONE place rather than to each field. There are nine
    password inputs across six views, and the snag's own warning was that
    six separate edits is how the sixth gets missed — as the create-case
    dropdown was on 4 Sep. Doing it here also means any password field
    added later is covered without anyone remembering to.

    THE BUTTON IS A SIBLING of the input, deliberately, not an input-group
    wrapper. Bootstrap shows a validation message with `.is-invalid ~
    .invalid-feedback`; moving the input inside a wrapper would take it out
    of that sibling relationship and silently kill the error messages on
    login and registration. A general sibling selector does not care that
    the button sits between them, so this arrangement leaves validation
    exactly as it was.

    ENHANCEMENT ONLY. With no JavaScript every field behaves as it did
    before: masked, and working.
--}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="password"]').forEach(function (input, index) {
        if (input.dataset.revealReady) {
            return;
        }
        input.dataset.revealReady = '1';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-link btn-sm p-0 mt-1';
        button.textContent = 'Show password';

        // The field may have no id (the delete-account one is the only
        // labelled-by-placeholder case), so fall back to a generated one
        // rather than pointing aria-controls at nothing.
        if (! input.id) {
            input.id = 'password-field-' + index;
        }
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-pressed', 'false');

        button.addEventListener('click', function () {
            const revealed = input.type === 'text';

            input.type = revealed ? 'password' : 'text';
            button.textContent = revealed ? 'Show password' : 'Hide password';
            button.setAttribute('aria-pressed', revealed ? 'false' : 'true');

            // Keep the caret where it was: flipping the type moves it to
            // the start in some browsers, which is maddening mid-word.
            const end = input.value.length;
            input.focus();
            try {
                input.setSelectionRange(end, end);
            } catch (e) {
                // Some browsers refuse setSelectionRange on certain input
                // types. Nothing here is worth an exception.
            }
        });

        // Straight after the input, and BEFORE any validation message, so
        // the error still reads as the last thing under the field.
        input.insertAdjacentElement('afterend', button);
    });
});
</script>

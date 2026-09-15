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

    THE VALIDATION MESSAGE HAS TO COME WITH IT. Bootstrap shows the
    message with `.is-invalid ~ .invalid-feedback`, so wrapping the input
    in an input-group and leaving the message outside would take the two
    out of that sibling relationship and silently kill the error text on
    login and registration — looking perfectly fine while doing it. The
    message is moved into the group alongside the input, which is what
    Bootstrap's own input-group validation expects, and `has-validation`
    goes on the group so the corners still meet.

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

        if (! input.id) {
            input.id = 'password-field-' + index;
        }

        // The validation message, if this field has one rendered.
        const feedback = input.nextElementSibling
            && input.nextElementSibling.classList.contains('invalid-feedback')
                ? input.nextElementSibling
                : null;

        const group = document.createElement('div');
        group.className = feedback ? 'input-group has-validation' : 'input-group';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-secondary';
        button.setAttribute('aria-controls', input.id);
        button.setAttribute('aria-pressed', 'false');
        button.setAttribute('aria-label', 'Show password');
        button.title = 'Show password';
        button.innerHTML = '<i class="bi bi-eye" aria-hidden="true"></i>';

        input.insertAdjacentElement('beforebegin', group);
        group.appendChild(input);
        group.appendChild(button);

        // Order inside the group matters: input, button, THEN the
        // message, so the sibling selector still finds it.
        if (feedback) {
            group.appendChild(feedback);
        }

        button.addEventListener('click', function () {
            const revealed = input.type === 'text';

            input.type = revealed ? 'password' : 'text';
            button.innerHTML = revealed
                ? '<i class="bi bi-eye" aria-hidden="true"></i>'
                : '<i class="bi bi-eye-slash" aria-hidden="true"></i>';
            button.setAttribute('aria-pressed', revealed ? 'false' : 'true');
            button.setAttribute('aria-label', revealed ? 'Show password' : 'Hide password');
            button.title = revealed ? 'Show password' : 'Hide password';

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
    });
});
</script>

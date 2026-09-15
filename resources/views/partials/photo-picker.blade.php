{{--
    The photo picker, shared by the create-case form and the reply form.

    Extracted 15 Sep 2026 when #19 gave replies attachments and Charlie
    found the reply form did not list what he had chosen. Writing a second
    picker would have put a second copy of the size arithmetic, the
    wording and the #58 total check next to the first — the very drift
    #68 was.

    Expects:
      $inputId   id of the file input
      $listId    id of the <ul> the chosen files are listed in
      $errorsId  id of the container client-side problems render into

    Staged rows are create-only. This script asks for them by attribute
    ([data-staged]) inside $listId, so on a form that has none the
    queries simply come back empty and every staged branch is inert —
    no flag needed.
--}}
{{--
    Photo selection list.

    Two jobs, both about the tenant seeing what is actually attached:

    1. Snag #43 — a file input REPLACES its entire FileList on each
       selection, so choosing one photo and then browsing again for a
       second silently discards the first. Standard HTML behaviour, and
       invisible server-side: store() receives one file and validates it
       happily. We accumulate into a DataTransfer instead, so a second
       browse ADDS.

       NOTE: a ceiling of 1 MASKS #43 without fixing it — at one permitted
       file, replacement is exactly what a tenant wants. The defect returns
       in full the moment the ceiling is raised, which is the point of it
       being configurable. Hence this runs at every ceiling.

    2. Show filename and size before sending, matching the preview and the
       case page. Keep the size format in step with App\Support\FileSize.

    ENHANCEMENT ONLY. If this never runs, the native input still submits
    and CaseController::store still enforces the ceiling, the mime types
    and the per-file size. No evidential guarantee rests on it.
--}}
<script>
(function () {
    const input = document.getElementById(@json($inputId));
    const list = document.getElementById(@json($listId));
    if (!input || !list) return;

    const ceiling = parseInt(input.dataset.photoCeiling || '0', 10);
    if (!ceiling) return;

    const maxBytes = parseInt(input.dataset.photoMaxBytes || '0', 10);
    // #58: the budget for the WHOLE selection. 0 means no total limit is
    // knowable, in which case only the per-file rule applies.
    const totalMaxBytes = parseInt(input.dataset.photoTotalMaxBytes || '0', 10);

    const errorBox = document.getElementById(@json($errorsId));

    function stagedRows() {
        return Array.from(list.querySelectorAll('[data-staged]'));
    }

    // Staged photos are the server's, not this script's — we hold no File
    // objects for them. Dropping them is therefore a server instruction,
    // not a DataTransfer edit. #53: each row carries its own hidden
    // keep_staged_photos[] input, so removing the row IS the instruction
    // and nothing else has to be kept in step.
    function dropStaged() {
        stagedRows().forEach(row => row.remove());
    }

    // #53: remove ONE. The old handler called dropStaged() for any Remove
    // button, ignoring which row was clicked, so the control said "remove
    // this photo" and the system heard "remove all photos".
    function dropOneStaged(button) {
        const row = button.closest('[data-staged]');
        if (row) row.remove();
    }

    // A validation error from the previous request describes files that are
    // no longer the selection. Clear it as soon as the tenant changes it.
    function clearErrors() {
        if (errorBox) errorBox.innerHTML = '';
        input.classList.remove('is-invalid');
    }

    // Client-side problems render in the same place, and read the same, as
    // the server's — the tenant should not be able to tell which stopped
    // them. Refusing a file here is not a lesser event than refusing it
    // there.
    function showProblems(messages) {
        if (!errorBox || !messages.length) return;

        messages.forEach(function (text) {
            const line = document.createElement('div');
            line.className = 'text-danger small mt-1';
            line.textContent = text;
            errorBox.appendChild(line);
        });

        input.classList.add('is-invalid');
    }

    list.addEventListener('click', function (event) {
        if (!event.target.matches('[data-remove-staged]')) return;
        dropOneStaged(event.target);
        clearErrors();
        render();
    });

    // Mirrors App\Support\FileSize::human().
    function humanSize(bytes) {
        if (bytes >= 1048576) return (Math.round(bytes / 1048576 * 10) / 10) + ' MB';
        return Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    let chosen = [];

    function sync() {
        const data = new DataTransfer();
        chosen.forEach(file => data.items.add(file));
        input.files = data.files;
        render();
    }

    function render() {
        // Never wipe server-rendered staged rows here — they are the record
        // of what is currently attached, and this script cannot recreate
        // them. dropStaged() is the only thing that removes them.
        const staged = stagedRows();
        list.innerHTML = '';
        staged.forEach(row => list.appendChild(row));

        if (!chosen.length && !staged.length) {
            const none = document.createElement('li');
            none.className = 'text-muted';
            none.textContent = 'No photos attached.';
            list.appendChild(none);
            return;
        }

        chosen.forEach((file, index) => {
            const row = document.createElement('li');
            row.className = 'd-flex align-items-center gap-2 mb-1';

            const name = document.createElement('span');
            name.textContent = file.name;

            const size = document.createElement('span');
            size.className = 'text-muted';
            size.textContent = '(' + humanSize(file.size) + ')';

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-link btn-sm p-0 text-danger';
            remove.textContent = 'Remove';
            remove.addEventListener('click', function () {
                chosen.splice(index, 1);
                sync();
            });

            row.append(name, size, remove);
            list.appendChild(row);
        });

        const attached = chosen.length + staged.length;

        if (attached >= ceiling) {
            const note = document.createElement('li');
            note.className = 'text-muted mt-1';
            note.textContent = attached + ' of ' + ceiling + ' — remove one to attach a different photo.';
            list.appendChild(note);
        }
    }

    input.addEventListener('change', function () {
        clearErrors();

        // #72 — new files ADD to whatever staged photos remain. This used
        // to call dropStaged(), matching the server's old replace rule, so
        // removing one of three and picking a replacement left the tenant
        // with just the replacement. Both halves changed together; if only
        // one had, the screen would promise something different from what
        // gets sent.

        const incoming = Array.from(input.files || []);

        // Refuse oversize HERE, before submitting. Otherwise one too-large
        // file fails server validation, the redirect loses the whole
        // selection (a browser cannot re-seed a file input), and the tenant
        // has to re-pick photos that were perfectly fine. The limit is the
        // one the machine will actually accept — min(our cap, PHP's
        // upload_max_filesize) — so this cannot promise more than the box
        // takes.
        const tooBig = maxBytes > 0 ? incoming.filter(f => f.size > maxBytes) : [];
        const usable = maxBytes > 0 ? incoming.filter(f => f.size <= maxBytes) : incoming;

        // #72 — the ceiling covers the WHOLE set: staged survivors plus
        // anything chosen since. Counting only `chosen` would let the
        // browser accept files the server then refuses.
        const room = ceiling - chosen.length - stagedRows().length;
        const withinCount = usable.slice(0, Math.max(0, room));

        // #58 — the TOTAL matters as much as each file. PHP refuses a
        // multipart body over post_max_size before any validation runs, so
        // an over-budget selection costs the tenant the entire submission
        // and the application never learns it happened. Accept files while
        // the running total fits and refuse the rest here, where it can be
        // explained, rather than at a 413 that cannot.
        let runningTotal = chosen.reduce((sum, f) => sum + f.size, 0);
        const accepted = [];
        const tooMuchTogether = [];

        withinCount.forEach(function (file) {
            if (totalMaxBytes > 0 && runningTotal + file.size > totalMaxBytes) {
                tooMuchTogether.push(file);
                return;
            }

            runningTotal += file.size;
            accepted.push(file);
        });

        chosen = chosen.concat(accepted);

        const problems = [];

        tooMuchTogether.forEach(function (file) {
            problems.push(
                'Photo "' + file.name + '" would take the total over ' + humanSize(totalMaxBytes) +
                ', which is the most this form can send at once. It has not been attached.'
            );
        });

        tooBig.forEach(function (file) {
            problems.push(
                'Photo "' + file.name + '" is ' + humanSize(file.size) +
                ' — each photo must be ' + humanSize(maxBytes) + ' or smaller. It has not been attached.'
            );
        });

        // Compared against withinCount, not accepted: a file left out for
        // the TOTAL has already been explained above, and saying it was
        // also refused for the count would be a second, wrong reason.
        if (usable.length > withinCount.length) {
            problems.push(
                (room > 0
                    ? 'You can add ' + room + (room === 1 ? ' more photo' : ' more photos') + ' — ' + ceiling + ' in total.'
                    : 'You already have ' + ceiling + (ceiling === 1 ? ' photo' : ' photos') + ' attached. Remove one before adding another.')
            );
        }

        showProblems(problems);
        sync();
    });

    render();
})();
</script>

@extends('layouts.app')

@section('title', 'Raise a Repair Case')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Raise a repair case</h1>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($properties->count() === 0)
        {{-- Was a dead end: the warning stated the blocker but gave no way out,
             leaving the user to find the properties page from the nav themselves. --}}
        <div class="alert alert-warning">
            <p>
                You need to register a property before you can raise a repair case.
                A case is always raised against a property, so we need that on file first.
            </p>
            <a href="{{ route('properties.create') }}" class="btn btn-primary">Register your property</a>
        </div>
    @else
        @php
            // Photo errors are deliberately EXCLUDED from this summary and
            // rendered next to the file input instead. Two reasons: the
            // tenant fixes a photo problem at the input, not at the top of
            // the page; and the script clears them the moment the selection
            // changes, which it cannot do to a summary that also carries
            // unrelated errors. Leaving them in both places showed the same
            // message twice and cleared only one copy.
            $summaryErrors = collect($errors->keys())
                ->reject(fn ($key) => $key === 'photos' || str_starts_with($key, 'photos.'))
                ->flatMap(fn ($key) => $errors->get($key))
                ->all();
        @endphp
        @if(count($summaryErrors) > 0)
            <div class="alert alert-danger">
                <strong>Please correct the following:</strong>
                <ul class="mb-0">
                    @foreach($summaryErrors as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('cases.store') }}" enctype="multipart/form-data" class="row g-3">
            @csrf

            {{-- One property is the norm and will be for most users — a tenant
                 rents one home. Asking them to "select" from a list of one is
                 pointless friction, especially arriving straight from having
                 just registered it. So: confirm it on a line, submit it hidden.
                 Ownership is enforced server-side by the property_id exists
                 rule (scoped to registered_by_user_id), so the hidden input is
                 not a trust boundary. The select returns for 2+ properties —
                 e.g. a tenant who has moved and stayed on the platform. --}}
            @if($properties->count() === 1)
                @php($property = $properties->first())
                <div class="col-md-12">
                    {{-- Label and address on one line to save vertical space on
                         mobile; the address wraps as a unit if the screen is
                         too narrow, with "Change" trailing it. --}}
                    <p class="mb-0">
                        <span class="text-muted">Property:</span>
                        <span class="fw-semibold">{{ $property->address_line1 }}@if($property->address_line2), {{ $property->address_line2 }}@endif, {{ $property->city }}, {{ $property->postcode }}</span>
                        <a href="{{ route('properties.index') }}" class="small ms-2">Change</a>
                    </p>
                    <input type="hidden" name="property_id" value="{{ $property->id }}">
                </div>
            @else
                <div class="col-md-12">
                    <label for="property_id" class="form-label">Property</label>
                    <select id="property_id" name="property_id" class="form-select @error('property_id') is-invalid @enderror" required>
                        <option value="">— select a property —</option>
                        @foreach($properties as $property)
                            {{-- The stored landlord rides on the option so
                                 selecting a property can show whose address
                                 the notice will go to. Display only — the
                                 server decides, and ignores anything typed
                                 for a property that already has one. --}}
                            <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)
                                    data-contact-name="{{ $property->currentLandlordContact?->name ?: $property->currentLandlordContact?->email }}"
                                    data-contact-email="{{ $property->currentLandlordContact?->email }}"
                                    data-property-url="{{ route('properties.edit', $property) }}">
                                {{ $property->address_line1 }}@if($property->address_line2), {{ $property->address_line2 }}@endif, {{ $property->postcode }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-md-8">
                <label for="category_key" class="form-label">Repair category</label>
                <select id="category_key" name="category_key" class="form-select @error('category_key') is-invalid @enderror" required>
                    <option value="">— select a category —</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->key }}" @selected(old('category_key') === $category->key)>
                            {{ $category->label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-4">
                <label for="severity" class="form-label">Severity</label>
                <select id="severity" name="severity" class="form-select @error('severity') is-invalid @enderror" required>
                    @foreach($severities as $severity)
                        <option value="{{ $severity->value }}" @selected(old('severity', 'routine') === $severity->value)>
                            {{ ucfirst($severity->value) }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12">
                <label for="description" class="form-label">Describe the problem</label>
                <textarea id="description" name="description" rows="4"
                          class="form-control @error('description') is-invalid @enderror"
                          placeholder="What is wrong, where is it, when did it start, and how is it affecting you?">{{ old('description') }}</textarea>
                <div class="form-text">This text is included in the letter sent to your landlord.</div>
            </div>

            <div class="col-12">
                @if($photoCeiling === 0)
                    {{-- Ceiling of 0 — say WHY. An input that simply vanishes
                         leaves a tenant who came to attach evidence with
                         nothing to read, and reads as a fault. --}}
                    <label class="form-label">Photos</label>
                    {{-- Deliberately does NOT claim attachments cause spam
                         filtering. Plausible, but this platform has not
                         measured it — a letter WITH an attachment scored
                         SCL:1 BCL:0 straight to Inbox on 2026-08-02. Says
                         what we did and why, without asserting a cause we
                         cannot evidence. --}}
                    <div class="alert alert-light border mb-0 py-2 small">
                        Photos can't be attached to this letter at the moment — we've turned
                        attachments off for now to make sure letters reach landlords' inboxes.
                        Please describe the problem in as much detail as you can instead; the
                        letter still carries your full description.
                    </div>
                @else
                    <label for="photos" class="form-label">
                        Photos (optional, up to {{ $photoCeiling }})
                    </label>
                    <input id="photos" name="photos[]" type="file" multiple
                           accept=".jpg,.jpeg,.png,.pdf"
                           data-photo-ceiling="{{ $photoCeiling }}"
                           data-photo-max-bytes="{{ $photoMaxBytes }}"
                           class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                    <div class="form-text">
                        JPG, PNG, or PDF. Each file must be under {{ $photoMaxLabel }}.
                    </div>

                    {{-- Photo errors live here rather than only in the summary
                         at the top, so the script can clear them the moment the
                         tenant changes their selection. A stale "too large"
                         message sitting above a freshly-chosen, perfectly good
                         photo reads as a live failure. --}}
                    <div id="photo-errors">
                        @foreach($errors->get('photos') as $message)
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @endforeach
                        @foreach($errors->get('photos.*') as $messages)
                            @foreach((array) $messages as $message)
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @endforeach
                        @endforeach
                    </div>

                    {{-- Staged photos are rendered server-side so an Edit
                         round-trip SHOWS what is attached. A browser cannot
                         re-seed a file input, so without this the tenant is
                         told a number at best and nothing at worst (#46). --}}
                    <ul id="photo-list" class="list-unstyled small mt-2 mb-0">
                        @foreach($stagedPhotos as $photo)
                            <li data-staged="1" class="d-flex align-items-center gap-2 mb-1">
                                <span>{{ $photo['original_filename'] ?? basename($photo['path']) }}</span>
                                <span class="text-muted">({{ \App\Support\FileSize::human((int) ($photo['size_bytes'] ?? 0)) }})</span>
                                <span class="badge text-bg-light">attached</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-remove-staged>Remove</button>
                            </li>
                        @endforeach
                    </ul>

                    {{-- Defaults ON whenever a staged set exists, so the safe
                         outcome — the evidence survives the round-trip —
                         is what happens with no JavaScript at all. Only
                         choosing new files or clicking Remove turns it off. --}}
                    <input type="hidden" id="keep-staged-photos" name="keep_staged_photos"
                           value="{{ count($stagedPhotos) > 0 ? 1 : 0 }}">
                @endif
            </div>

            <hr class="mt-4">
            <h2 class="h5">Landlord or letting agent</h2>

            {{-- Model A: the landlord belongs to the PROPERTY, not the
                 case. A property that already has one shows it read-only
                 and cannot be overridden here — the server excludes these
                 fields from validation entirely in that case, so what is
                 shown is what is served. Correcting it is a property edit,
                 which is the whole of snag #24.

                 With one property the decision is made server-side and no
                 JavaScript is involved. With several, the block below is
                 toggled on selection; the server remains authoritative
                 either way. --}}
            @php($singleProperty = $properties->count() === 1 ? $properties->first() : null)
            @php($inheritedContact = $singleProperty?->currentLandlordContact)

            <div class="col-12" id="landlord-inherited"
                 @class(['d-none' => ! $inheritedContact])>
                <div class="border rounded p-3 bg-light">
                    <p class="mb-1">
                        <span class="text-muted">This property&rsquo;s landlord:</span>
                        <span class="fw-semibold" data-inherited-name>{{ $inheritedContact?->name ?: $inheritedContact?->email }}</span>
                    </p>
                    <p class="mb-1 small text-muted" data-inherited-email>{{ $inheritedContact?->email }}</p>
                    <p class="mb-0 small">
                        The notice will be served on this address.
                        <a href="{{ $singleProperty ? route('properties.edit', $singleProperty) : route('properties.index') }}">Correct it on the property</a>
                        if it is wrong.
                    </p>
                </div>
            </div>

            <div class="row g-3" id="landlord-fields"
                 @class(['d-none' => (bool) $inheritedContact])>
                <div class="col-md-6">
                    <label for="landlord_email" class="form-label">Email address</label>
                    <input id="landlord_email" name="landlord_email" type="email"
                           class="form-control @error('landlord_email') is-invalid @enderror"
                           value="{{ old('landlord_email') }}" @required(! $inheritedContact)>
                </div>

                <div class="col-md-6">
                    <label for="landlord_name" class="form-label">Name</label>
                    <input id="landlord_name" name="landlord_name" type="text" maxlength="255"
                           class="form-control @error('landlord_name') is-invalid @enderror"
                           value="{{ old('landlord_name') }}">
                    {{-- The fallback lives in CaseController::resolveLandlordName,
                         which the preview AND the send both go through, and the
                         templates open "Dear {{landlord_name}},". One source, so
                         the name shown on the preview is the name that is sent
                         (snag #49). --}}
                    <div class="form-text">
                        Optional. Left blank, the letter opens &ldquo;Dear Sir or Madam&rdquo;.
                        This becomes the property&rsquo;s landlord, so later cases here will use it too.
                    </div>
                </div>

                <div class="col-md-4">
                    <label for="landlord_role" class="form-label">Role</label>
                    <select id="landlord_role" name="landlord_role" class="form-select @error('landlord_role') is-invalid @enderror" @required(! $inheritedContact)>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected(old('landlord_role', 'landlord') === $role->value)>
                                {{ ucfirst($role->value) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-8">
                    <label for="organisation_name" class="form-label">Organisation name (if agent)</label>
                    <input id="organisation_name" name="organisation_name" type="text" maxlength="255"
                           class="form-control @error('organisation_name') is-invalid @enderror"
                           value="{{ old('organisation_name') }}">
                </div>
            </div>

            <div class="col-12 d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary">Send the first notice</button>
                <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    @endif
</div>
@endsection

@section('scripts')
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
    const input = document.getElementById('photos');
    const list = document.getElementById('photo-list');
    if (!input || !list) return;

    const ceiling = parseInt(input.dataset.photoCeiling || '0', 10);
    if (!ceiling) return;

    const maxBytes = parseInt(input.dataset.photoMaxBytes || '0', 10);

    const keepFlag = document.getElementById('keep-staged-photos');
    const errorBox = document.getElementById('photo-errors');

    function stagedRows() {
        return Array.from(list.querySelectorAll('[data-staged]'));
    }

    // Staged photos are the server's, not this script's — we hold no File
    // objects for them. Dropping them is therefore a server instruction
    // (the keep flag), not a DataTransfer edit.
    function dropStaged() {
        stagedRows().forEach(row => row.remove());
        if (keepFlag) keepFlag.value = '0';
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
        dropStaged();
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

        if (chosen.length >= ceiling) {
            const note = document.createElement('li');
            note.className = 'text-muted mt-1';
            note.textContent = chosen.length + ' of ' + ceiling + ' — remove one to attach a different photo.';
            list.appendChild(note);
        }
    }

    input.addEventListener('change', function () {
        clearErrors();

        // Choosing new files REPLACES the staged set — same rule the server
        // applies in resolveStagedPhotos(), so the screen cannot promise
        // something different from what will be sent.
        if (stagedRows().length) {
            dropStaged();
        }

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

        const room = ceiling - chosen.length;
        const accepted = usable.slice(0, Math.max(0, room));
        chosen = chosen.concat(accepted);

        const problems = [];

        tooBig.forEach(function (file) {
            problems.push(
                'Photo "' + file.name + '" is ' + humanSize(file.size) +
                ' — each photo must be ' + humanSize(maxBytes) + ' or smaller. It has not been attached.'
            );
        });

        if (usable.length > accepted.length) {
            problems.push(
                'You can attach up to ' + ceiling + (ceiling === 1 ? ' photo' : ' photos') +
                '. Remove one first if you want to swap it for a different photo.'
            );
        }

        showProblems(problems);
        sync();
    });

    render();
})();
</script>

<script>
/*
    Landlord block toggle, for tenants with more than one property.

    Display only. The server excludes the landlord fields from validation
    whenever the chosen property already has a contact, so a tenant with
    JavaScript off — or one who edits the DOM — gets exactly the same
    outcome: the property's stored landlord is served, and anything typed
    here is discarded before it is looked at.
*/
(function () {
    const select = document.getElementById('property_id');
    const inherited = document.getElementById('landlord-inherited');
    const fields = document.getElementById('landlord-fields');

    if (!select || !inherited || !fields) {
        return;
    }

    const nameEl = inherited.querySelector('[data-inherited-name]');
    const emailEl = inherited.querySelector('[data-inherited-email]');
    const linkEl = inherited.querySelector('a');
    const required = ['landlord_email', 'landlord_role'];

    function sync() {
        const option = select.selectedOptions[0];
        const email = option ? option.dataset.contactEmail : '';

        if (email) {
            nameEl.textContent = option.dataset.contactName || email;
            emailEl.textContent = email;
            linkEl.href = option.dataset.propertyUrl;
        }

        inherited.classList.toggle('d-none', !email);
        fields.classList.toggle('d-none', !!email);

        // Drop required off hidden inputs, or the browser blocks submit on
        // a field the tenant cannot even see.
        required.forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.required = !email;
            }
        });
    }

    select.addEventListener('change', sync);
    sync();
})();
</script>
@endsection

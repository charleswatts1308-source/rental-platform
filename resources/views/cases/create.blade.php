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
                                    data-contact-role="{{ $property->currentLandlordContact?->role->value }}"
                                    data-property-url="{{ route('properties.contact.edit', $property) }}">
                                {{ $property->address_line1 }}@if($property->address_line2), {{ $property->address_line2 }}@endif, {{ $property->postcode }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="col-12">
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
                    <label for="photos" class="form-label">Photos (optional)</label>
                    <input id="photos" name="photos[]" type="file" multiple
                           accept=".jpg,.jpeg,.png,.pdf"
                           data-photo-ceiling="{{ $photoCeiling }}"
                           data-photo-max-bytes="{{ $photoMaxBytes }}"
                           data-photo-total-max-bytes="{{ $photoTotalMaxBytes }}"
                           class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                    {{-- All three limits, stated. The count used to sit in a
                         bracket in the label and the TOTAL was not stated at
                         all — so a tenant could pick three files, satisfy every
                         limit the screen named, and still be refused as a set.
                         Raised 15 Sep by Charlie: "the UI does not mention any
                         max limit". --}}
                    <div class="form-text">
                        @include('partials.photo-limits', [
                            'ceiling' => $photoCeiling,
                            'perFileLabel' => $photoMaxLabel,
                            'totalBytes' => $photoTotalMaxBytes,
                            'totalLabel' => $photoTotalLabel,
                        ])
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
                                {{-- #53: the keep instruction rides INSIDE the row.
                                     Remove deletes the row, which takes this input
                                     with it, so the server is told precisely which
                                     photo went. It used to be one flag for the whole
                                     set, so removing one of two removed both. --}}
                                <input type="hidden" name="keep_staged_photos[]" value="{{ $photo['path'] }}">
                                <span>{{ $photo['original_filename'] ?? basename($photo['path']) }}</span>
                                <span class="text-muted">({{ \App\Support\FileSize::human((int) ($photo['size_bytes'] ?? 0)) }})</span>
                                <span class="badge text-bg-light">attached</span>
                                <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-remove-staged>Remove</button>
                            </li>
                        @endforeach
                    </ul>

                    {{-- The sentinel. Without it, removing EVERY row would leave
                         the field absent, and absent means KEEP EVERYTHING — the
                         safe default that stops a forgetful caller wiping a
                         tenant's evidence. This empty entry keeps the field
                         present so "remove them all" can still be said. It sits
                         outside the list, so no Remove click can take it. --}}
                    <input type="hidden" name="keep_staged_photos[]" value="">
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

            {{-- Layout classes go INSIDE @class. A literal class="..."
                 alongside @class emits the attribute twice, and a browser
                 keeps the first and silently drops the second — which hid
                 nothing and showed the edit fields next to the read-only
                 panel. Caught by walking the page, not by a test. --}}
            <div id="landlord-inherited"
                 @class(['col-12', 'd-none' => ! $inheritedContact])>
                <div class="border rounded p-3 bg-light">
                    <p class="mb-1">
                        {{-- Titled by the contact's STORED ROLE, the same way
                             the case page is (#2). Hardcoding "landlord" told a
                             tenant who had just set the contact to Agent that it
                             was a landlord — a surface contradicting what the
                             user had entered one screen earlier. --}}
                        <span class="text-muted" data-inherited-role>This property&rsquo;s {{ $inheritedContact?->role->value ?: "landlord" }}:</span>
                        <span class="fw-semibold" data-inherited-name>{{ $inheritedContact?->name ?: $inheritedContact?->email }}</span>
                    </p>
                    <p class="mb-1 small text-muted" data-inherited-email>{{ $inheritedContact?->email }}</p>
                    <p class="mb-0 small">
                        The notice will be served on this address.
                        <a href="{{ $singleProperty ? route('properties.contact.edit', $singleProperty) : route('properties.index') }}">Correct it on the property</a>
                        if it is wrong.
                    </p>
                </div>
            </div>

            <div id="landlord-fields"
                 @class(['row', 'g-3', 'd-none' => (bool) $inheritedContact])>
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
@include('partials.photo-picker', [
    'inputId' => 'photos',
    'listId' => 'photo-list',
    'errorsId' => 'photo-errors',
])

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
    const roleEl = inherited.querySelector('[data-inherited-role]');
    const linkEl = inherited.querySelector('a');
    const required = ['landlord_email', 'landlord_role'];

    function sync() {
        const option = select.selectedOptions[0];
        const email = option ? option.dataset.contactEmail : '';

        if (email) {
            nameEl.textContent = option.dataset.contactName || email;
            emailEl.textContent = email;
            linkEl.href = option.dataset.propertyUrl;

            // Relabel as well as refill: switching to a property whose
            // contact is an agent must not leave the previous property's
            // word standing.
            if (roleEl) {
                const role = option.dataset.contactRole || 'landlord';
                roleEl.textContent = 'This property’s ' + role + ':';
            }
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

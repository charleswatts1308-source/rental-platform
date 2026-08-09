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
        @if($errors->any())
            <div class="alert alert-danger">
                <strong>Please correct the following:</strong>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
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
                            <option value="{{ $property->id }}" @selected(old('property_id') == $property->id)>
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
                    @if(($stagedPhotoCount ?? 0) > 0)
                        <div class="form-text text-success mb-1">
                            @if($stagedPhotoCount === 1)
                                Your photo is saved — you don't need to re-attach it unless you want to change your selection.
                            @else
                                Your {{ $stagedPhotoCount }} photos are saved — you don't need to re-attach them unless you want to change your selection.
                            @endif
                        </div>
                    @endif
                    <input id="photos" name="photos[]" type="file" multiple
                           accept=".jpg,.jpeg,.png,.pdf"
                           data-photo-ceiling="{{ $photoCeiling }}"
                           class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                    <div class="form-text">
                        JPG, PNG, or PDF. Each file must be under {{ $photoMaxLabel }}.
                    </div>
                    {{-- Populated by the script below. Stays empty without JS,
                         where the native control's own summary applies. --}}
                    <ul id="photo-list" class="list-unstyled small mt-2 mb-0"></ul>
                @endif
            </div>

            <hr class="mt-4">
            <h2 class="h5">Landlord or letting agent</h2>

            <div class="col-md-6">
                <label for="landlord_email" class="form-label">Email address</label>
                <input id="landlord_email" name="landlord_email" type="email"
                       class="form-control @error('landlord_email') is-invalid @enderror"
                       value="{{ old('landlord_email') }}" required>
            </div>

            <div class="col-md-6">
                <label for="landlord_name" class="form-label">Name</label>
                <input id="landlord_name" name="landlord_name" type="text" maxlength="255"
                       class="form-control @error('landlord_name') is-invalid @enderror"
                       value="{{ old('landlord_name') }}">
            </div>

            <div class="col-md-4">
                <label for="landlord_role" class="form-label">Role</label>
                <select id="landlord_role" name="landlord_role" class="form-select @error('landlord_role') is-invalid @enderror" required>
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
        list.innerHTML = '';

        if (!chosen.length) {
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
        // Anything over the ceiling is dropped here AND refused by the
        // server; the tenant is told rather than left to wonder.
        const incoming = Array.from(input.files || []);
        const room = ceiling - chosen.length;

        chosen = chosen.concat(incoming.slice(0, Math.max(0, room)));

        if (incoming.length > Math.max(0, room)) {
            window.alert(
                'You can attach up to ' + ceiling + (ceiling === 1 ? ' photo' : ' photos') + '. ' +
                'Remove one first if you want to swap it for a different photo.'
            );
        }

        sync();
    });

    render();
})();
</script>
@endsection

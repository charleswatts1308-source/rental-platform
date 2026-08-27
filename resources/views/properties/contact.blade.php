@extends('layouts.app')

@section('title', 'Landlord Details')

@section('content')
<div class="container py-4">
    <h1 class="mb-1">Landlord or letting agent</h1>
    <p class="text-muted">
        {{ $property->address_line1 }}@if($property->address_line2), {{ $property->address_line2 }}@endif,
        {{ $property->city }}, {{ $property->postcode }}
    </p>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

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

    {{-- Says plainly what saving does and does not do. A tenant correcting
         a typo needs to know that letters already sent are unaffected —
         they are the record, and they are not being rewritten. --}}
    <div class="alert alert-light border">
        <p class="mb-1">This is the address repair notices for this property are served on.</p>
        <p class="mb-0 small text-muted">
            Correcting it changes where the <strong>next</strong> letter goes on every
            open case here. Letters already sent are unchanged — they stay on record
            exactly as they were sent. Nothing is sent when you save.
        </p>
    </div>

    <form method="POST" action="{{ route('properties.contact.update', $property) }}" class="row g-3">
        @csrf
        @method('PATCH')

        <div class="col-md-6">
            <label for="email" class="form-label">Email address</label>
            <input id="email" name="email" type="email" required
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email', $contact?->email) }}">
        </div>

        <div class="col-md-6">
            <label for="name" class="form-label">Name</label>
            <input id="name" name="name" type="text" maxlength="255"
                   class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name', $contact?->name) }}">
            <div class="form-text">Left blank, letters open &ldquo;Dear Sir or Madam&rdquo;.</div>
        </div>

        <div class="col-md-4">
            <label for="role" class="form-label">Role</label>
            <select id="role" name="role" class="form-select @error('role') is-invalid @enderror" required>
                @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $contact?->role->value ?? 'landlord') === $role->value)>
                        {{ ucfirst($role->value) }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-8">
            <label for="organisation_name" class="form-label">Organisation name (if agent)</label>
            <input id="organisation_name" name="organisation_name" type="text" maxlength="255"
                   class="form-control @error('organisation_name') is-invalid @enderror"
                   value="{{ old('organisation_name', $contact?->organisation_name) }}">
        </div>

        <div class="col-12">
            <hr class="mt-3">
            <h2 class="h5">Postal address</h2>
            {{-- Recorded for reference only. Notices are served by email;
                 this is not printed on anything. --}}
            <p class="text-muted small">
                Optional, and kept for your records only — letters are sent by email and
                this address does not appear on them.
            </p>
        </div>

        <div class="col-md-6">
            <label for="address_line1" class="form-label">Address line 1</label>
            <input id="address_line1" name="address_line1" type="text" maxlength="255"
                   class="form-control @error('address_line1') is-invalid @enderror"
                   value="{{ old('address_line1', $contact?->address_line1) }}">
        </div>

        <div class="col-md-6">
            <label for="address_line2" class="form-label">Address line 2</label>
            <input id="address_line2" name="address_line2" type="text" maxlength="255"
                   class="form-control @error('address_line2') is-invalid @enderror"
                   value="{{ old('address_line2', $contact?->address_line2) }}">
        </div>

        <div class="col-md-6">
            <label for="city" class="form-label">Town or city</label>
            <input id="city" name="city" type="text" maxlength="100"
                   class="form-control @error('city') is-invalid @enderror"
                   value="{{ old('city', $contact?->city) }}">
        </div>

        <div class="col-md-6">
            <label for="postcode" class="form-label">Postcode</label>
            <input id="postcode" name="postcode" type="text" maxlength="20"
                   class="form-control @error('postcode') is-invalid @enderror"
                   value="{{ old('postcode', $contact?->postcode) }}">
        </div>

        <div class="col-12 d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">
                {{ $contact ? 'Save correction' : 'Save landlord details' }}
            </button>
            <a href="{{ route('properties.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    @if($history->count() > 0)
        <hr class="mt-5">
        <h2 class="h5">History</h2>
        <p class="text-muted small">
            Every version of this property&rsquo;s landlord details, newest first.
        </p>

        <ul class="list-group">
            @foreach($history as $version)
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div>
                            <p class="mb-1">
                                <span class="fw-semibold">{{ $version->name ?: '(no name given)' }}</span>
                                — {{ $version->email }}
                            </p>
                            <p class="mb-0 small text-muted">
                                {{ ucfirst($version->role->value) }}@if($version->organisation_name), {{ $version->organisation_name }}@endif
                                @if($version->hasPostalAddress())
                                    &middot; {{ implode(', ', $version->postalAddressLines()) }}
                                @endif
                            </p>
                            <p class="mb-0 small text-muted">
                                @if($version->source === \App\Enums\ContactSource::Backfilled)
                                    {{-- Not an edit anybody made. The system never
                                         recorded when a landlord's address changed,
                                         only which address each case used, so this
                                         date is reconstructed from the case records.
                                         Saying so beats presenting an inference as
                                         a decision. --}}
                                    Reconstructed from earlier cases
                                @else
                                    Entered by {{ $version->createdBy?->name ?? 'a former user' }}
                                @endif
                                on {{ $version->effective_from->format('j M Y') }}
                                @if($version->superseded_at)
                                    &middot; replaced {{ $version->superseded_at->format('j M Y') }}
                                @endif
                            </p>
                        </div>
                        @if($version->isCurrent())
                            <span class="badge text-bg-primary">Current</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection

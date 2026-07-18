@extends('layouts.app')

@section('title', 'Raise a Repair Case')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Raise a repair case</h1>

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
                <label for="photos" class="form-label">Photos (optional, up to 6)</label>
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
                       class="form-control @error('photos') is-invalid @enderror @error('photos.*') is-invalid @enderror">
                <div class="form-text">JPG, PNG, or PDF. Each file must be under 2MB.</div>
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

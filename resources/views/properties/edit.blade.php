@extends('layouts.app')

@section('title', 'Edit Property')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Edit property</h1>

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

    <form method="POST" action="{{ route('properties.update', $property) }}" class="row g-3" enctype="multipart/form-data">
        @csrf
        @method('PATCH')
        @include('properties._fields', ['property' => $property])

        <div class="col-12 d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Save changes</button>
            {{-- Cancel returns to /cases, not the property list: since 4 Oct 2026
                 these forms are reached from a property heading on the Cases
                 page, and landing on a page the user has never seen is how a
                 Cancel loses someone. --}}
            <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    {{-- Pages already held. OUTSIDE the edit form, because removing one
         is its own action with its own verb - folding deletes into a
         Save would mean a tenant who changed their mind about a page had
         to save the whole form to act on it, and a tenant who cancelled
         would silently keep it. --}}
    @if($property->documents->isNotEmpty())
        <h2 class="h6 mt-5">Lease agreement &mdash; pages held</h2>
        <p class="text-muted small">
            Private to you. Never sent to your landlord and never attached to a case.
        </p>
        <ul class="list-group mb-3">
            @foreach($property->documents as $document)
                <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>
                        <a href="{{ route('properties.documents.show', [$property, $document]) }}" target="_blank" rel="noopener">
                            {{ $document->displayLabel() }}
                        </a>
                        <span class="text-muted small">{{ $document->original_filename }}</span>
                    </span>
                    <form method="POST" action="{{ route('properties.documents.destroy', [$property, $document]) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection


@extends('layouts.app')

@section('title', 'Register a Property')

@section('content')
<div class="container py-4">
    <h1 class="mb-4">Register a property</h1>

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

    <form method="POST" action="{{ route('properties.store') }}" class="row g-3">
        @csrf
        @include('properties._fields', ['property' => null])

        <div class="col-12 d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary">Register property</button>
            {{-- Cancel returns to /cases, not the property list: since 4 Oct 2026
                 these forms are reached from a property heading on the Cases
                 page, and landing on a page the user has never seen is how a
                 Cancel loses someone. --}}
            <a href="{{ route('cases.index') }}" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection

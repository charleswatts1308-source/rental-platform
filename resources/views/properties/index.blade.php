@extends('layouts.app')

@section('title', 'My Properties')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Properties</h1>
        <a href="{{ route('properties.create') }}" class="btn btn-primary">Register a property</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($properties->count() === 0)
        <div class="alert alert-secondary">
            <p>
                You haven't registered any properties yet. Register the property you rent —
                then you can raise a repair case against it.
            </p>
            <a href="{{ route('properties.create') }}" class="btn btn-primary">Register your property</a>
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Address</th>
                        <th scope="col">City</th>
                        <th scope="col">Postcode</th>
                        <th scope="col">Registered</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($properties as $property)
                        <tr>
                            <td>
                                {{ $property->address_line1 }}@if($property->address_line2)<br><span class="text-muted">{{ $property->address_line2 }}</span>@endif
                            </td>
                            <td>{{ $property->city }}</td>
                            <td><code>{{ $property->postcode }}</code></td>
                            <td>{{ $property->created_at->format('d M Y') }}</td>
                            <td class="text-end">
                                <a href="{{ route('properties.edit', $property) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

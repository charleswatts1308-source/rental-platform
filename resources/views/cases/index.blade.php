@extends('layouts.app')

@section('title', 'My Repair Cases')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Repair Cases</h1>
        <a href="{{ route('cases.create') }}" class="btn btn-primary">Raise a new case</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($cases->count() === 0)
        <div class="alert alert-secondary">
            You haven't raised any repair cases yet. Use the button above to send a notice to your landlord.
        </div>
    @else
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th scope="col">Reference</th>
                        <th scope="col">Property</th>
                        <th scope="col">Issue</th>
                        <th scope="col">Severity</th>
                        <th scope="col">Stage</th>
                        <th scope="col">Status</th>
                        <th scope="col">Opened</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cases as $case)
                        <tr>
                            <td><a href="{{ route('cases.show', $case->url_slug) }}"><code class="fs-6">{{ $case->url_slug }}</code></a></td>
                            <td>
                                {{ $case->property->address_line1 }},
                                <span class="text-muted">{{ $case->property->postcode }}</span>
                            </td>
                            <td>{{ $case->category?->label ?? $case->category_key }}</td>
                            <td>
                                <span class="badge bg-secondary text-uppercase">{{ $case->severity->value }}</span>
                            </td>
                            <td>{{ $case->current_stage }}</td>
                            <td>
                                <span class="badge bg-info text-dark">{{ str_replace('_', ' ', $case->status->value) }}</span>
                            </td>
                            <td>{{ $case->opened_at?->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection

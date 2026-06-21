@extends('layouts.app')
@section('title', 'Admin - Case Oversight')
@section('content')
<h1 class="mb-4">Case Oversight</h1>

<p class="text-muted mb-3">
    Read-only view of every case: state, ball, and clock position. There are no
    actions here by design — a case's state only ever changes through a recorded
    cause in its event trail.
</p>

<div class="table-responsive">
    <table class="table table-striped table-sm align-middle">
        <thead>
            <tr>
                <th scope="col">Reference</th>
                <th scope="col">Property</th>
                <th scope="col">Tenant</th>
                <th scope="col">Status</th>
                <th scope="col">Ball</th>
                <th scope="col">Opened</th>
                <th scope="col"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($cases as $case)
                <tr>
                    <td><code>{{ $case->url_slug }}</code></td>
                    <td>{{ $case->property?->address_line1 }}, {{ $case->property?->postcode }}</td>
                    <td>{{ $case->tenant?->name }}</td>
                    <td><span class="badge bg-info text-dark">{{ str_replace('_', ' ', $case->status->value) }}</span></td>
                    <td>{{ $case->ball_with ?? '—' }}</td>
                    <td class="text-nowrap">{{ $case->opened_at?->format('d M Y') ?? '—' }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.cases.show', $case) }}" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">No cases</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Admin - Rentals')

@section('content')
<h1 class="mb-4">Rentals</h1>

<p class="text-muted mb-3">Showing {{ $rentals->count() }} most recent rentals</p>

<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>User ID</th>
                <th>User Name</th>
                <th>Services</th>
                <th>Property</th>
                <th>Agent</th>
                <th>Landlord</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rentals as $rental)
                <tr>
                    <td>{{ $rental->rental_id }}</td>
                    <td>{{ $rental->date_created ? $rental->date_created->format('d M Y') : '-' }}</td>
                    <td>{{ $rental->user_id }}</td>
                    <td>{{ $rental->user_name }}</td>
                    <td>
                        @if($rental->serv_req1_ll_status)
                            <span class="badge bg-info">LL Status</span>
                        @endif
                        @if($rental->serv_req2_ll_pu)
                            <span class="badge bg-info">LL PU</span>
                        @endif
                        @if(!$rental->serv_req1_ll_status && !$rental->serv_req2_ll_pu)
                            <span class="text-muted">-</span>
                        @endif
                    </td>
                    <td>{{ $rental->rental_line1 ?: '-' }}</td>
                    <td>{{ $rental->agent_company_name ?: $rental->agent_contact_name ?: '-' }}</td>
                    <td>{{ $rental->landlord_company_name ?: $rental->landlord_contact_name ?: '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted">No rentals found</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

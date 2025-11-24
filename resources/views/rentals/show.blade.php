@extends('layouts.app')

@section('title', 'Rental Details')

@section('content')
<style>
    .rental-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        max-width: 1200px;
    }

    .rental-header {
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #007bff;
    }

    .rental-name {
        font-size: 1.5rem;
        font-weight: 600;
        color: #007bff;
        margin: 0 0 10px 0;
    }

    .rental-id {
        color: #6c757d;
        font-size: 0.9rem;
    }

    .details-section {
        margin-bottom: 30px;
    }

    .section-header {
        font-size: 1.1rem;
        font-weight: 600;
        color: #495057;
        text-transform: uppercase;
        margin-bottom: 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid #dee2e6;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .detail-group {
        background: #f8f9fa;
        border-radius: 4px;
        padding: 15px;
    }

    .detail-group h6 {
        font-size: 1rem;
        font-weight: 600;
        color: #495057;
        margin-bottom: 10px;
        text-transform: uppercase;
    }

    .detail-item {
        margin-bottom: 8px;
        line-height: 1.4;
    }

    .detail-label {
        font-weight: 500;
        color: #6c757d;
        font-size: 0.9rem;
    }

    .detail-value {
        color: #495057;
        margin-left: 5px;
    }

    .service-requests {
        background: #e9ecef;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 20px;
    }

    .service-item {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        font-weight: 500;
    }

    .service-status {
        margin-left: 10px;
        font-size: 1.2rem;
    }

    .status-checked {
        color: #28a745;
    }

    .status-unchecked {
        color: #6c757d;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        padding-top: 20px;
        border-top: 1px solid #dee2e6;
        margin-top: 20px;
    }

    .btn-action {
        padding: 10px 20px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        display: inline-block;
    }

    .btn-edit {
        background: #007bff;
        color: white;
    }

    .btn-edit:hover {
        background: #0056b3;
        color: white;
        text-decoration: none;
    }

    .btn-files {
        background: #17a2b8;
        color: white;
    }

    .btn-files:hover {
        background: #117a8b;
        color: white;
        text-decoration: none;
    }

    .btn-delete {
        background: #dc3545;
        color: white;
    }

    .btn-delete:hover {
        background: #c82333;
        color: white;
    }

    .btn-back {
        background: #6c757d;
        color: white;
    }

    .btn-back:hover {
        background: #5a6268;
        color: white;
        text-decoration: none;
    }

    .alert-success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
</style>

<h1>Rental Details</h1>

<div class="rental-card">
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Header Section -->
    <div class="rental-header">
        <h2 class="rental-name">
            @php
                $addressParts = collect([$rental->rental_line1, $rental->rental_post_code])
                    ->filter()
                    ->toArray();
                $displayAddress = count($addressParts) > 0 ? implode(', ', $addressParts) : 'Rental Property';
            @endphp
            {{ $displayAddress }}
        </h2>
        <div class="rental-id">
            ID: {{ $rental->rental_id }} | Created: {{ $rental->date_created ? $rental->date_created->format('d M Y') : 'N/A' }}
        </div>
    </div>

    <!-- Main Details Grid -->
    <div class="details-grid">
        <!-- Property Address -->
        <div class="detail-group">
            <h6>Property Address</h6>
            @if($rental->rental_line1)
                <div class="detail-item">{{ $rental->rental_line1 }}</div>
            @endif
            @if($rental->rental_line2)
                <div class="detail-item">{{ $rental->rental_line2 }}</div>
            @endif
            @if($rental->rental_city)
                <div class="detail-item">{{ $rental->rental_city }}</div>
            @endif
            @if($rental->rental_post_code)
                <div class="detail-item"><strong>{{ $rental->rental_post_code }}</strong></div>
            @endif
        </div>

        <!-- Agent -->
        <div class="detail-group">
            <h6>Agent</h6>
            @if($rental->agent_contact_name || $rental->agent_company_name)
                @if($rental->agent_contact_name)
                    <div class="detail-item">{{ $rental->agent_contact_name }}</div>
                @endif
                @if($rental->agent_company_name)
                    <div class="detail-item">{{ $rental->agent_company_name }}</div>
                @endif
                @if($rental->agent_contact_email)
                    <div class="detail-item">{{ $rental->agent_contact_email }}</div>
                @endif
                @if($rental->agent_line1)
                    <div class="detail-item">{{ $rental->agent_line1 }}</div>
                @endif
                @if($rental->agent_line2)
                    <div class="detail-item">{{ $rental->agent_line2 }}</div>
                @endif
                @if($rental->agent_city)
                    <div class="detail-item">{{ $rental->agent_city }}</div>
                @endif
                @if($rental->agent_post_code)
                    <div class="detail-item">{{ $rental->agent_post_code }}</div>
                @endif
            @else
                <div class="detail-item text-muted">No agent information</div>
            @endif
        </div>

        <!-- Landlord -->
        <div class="detail-group">
            <h6>Landlord</h6>
            @if($rental->landlord_contact_name || $rental->landlord_company_name)
                @if($rental->landlord_contact_name)
                    <div class="detail-item">{{ $rental->landlord_contact_name }}</div>
                @endif
                @if($rental->landlord_company_name)
                    <div class="detail-item">{{ $rental->landlord_company_name }}</div>
                @endif
                @if($rental->landlord_contact_email)
                    <div class="detail-item">{{ $rental->landlord_contact_email }}</div>
                @endif
                @if($rental->landlord_line1)
                    <div class="detail-item">{{ $rental->landlord_line1 }}</div>
                @endif
                @if($rental->landlord_line2)
                    <div class="detail-item">{{ $rental->landlord_line2 }}</div>
                @endif
                @if($rental->landlord_city)
                    <div class="detail-item">{{ $rental->landlord_city }}</div>
                @endif
                @if($rental->landlord_post_code)
                    <div class="detail-item">{{ $rental->landlord_post_code }}</div>
                @endif
            @else
                <div class="detail-item text-muted">No landlord information</div>
            @endif
        </div>

        <!-- Lease Details -->
        <div class="detail-group">
            <h6>Lease Details</h6>
            @if($rental->lease_type)
                <div class="detail-item">
                    <span class="detail-label">Lease Type:</span>
                    <span class="detail-value">{{ $rental->lease_type }}</span>
                </div>
            @endif
            @if($rental->lease_expiry_date)
                <div class="detail-item">
                    <span class="detail-label">Expires:</span>
                    <span class="detail-value">{{ \Carbon\Carbon::parse($rental->lease_expiry_date)->format('d M Y') }}</span>
                </div>
            @endif
            @if($rental->no_of_tenants)
                <div class="detail-item">
                    <span class="detail-label">Tenants:</span>
                    <span class="detail-value">{{ $rental->no_of_tenants }}</span>
                </div>
            @endif
            @if($rental->rental_type)
                <div class="detail-item">
                    <span class="detail-label">Type:</span>
                    <span class="detail-value">{{ $rental->rental_type }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Service Requests -->
    <div class="details-section">
        <h5 class="section-header">Service Requests</h5>
        <div class="service-requests">
            <div class="service-item">
                LL Status:
                <span class="service-status {{ $rental->serv_req1_ll_status ? 'status-checked' : 'status-unchecked' }}">
                    {{ $rental->serv_req1_ll_status ? '✓' : '—' }}
                </span>
            </div>
            <div class="service-item">
                LL PU:
                <span class="service-status {{ $rental->serv_req2_ll_pu ? 'status-checked' : 'status-unchecked' }}">
                    {{ $rental->serv_req2_ll_pu ? '✓' : '—' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="{{ route('rentals.edit', $rental) }}" class="btn-action btn-edit">Edit</a>
        <a href="#" class="btn-action btn-files">Manage Files</a>
        <form method="POST" action="{{ route('rentals.destroy', $rental) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this rental?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-action btn-delete">Delete</button>
        </form>
        <a href="{{ route('rentals.index') }}" class="btn-action btn-back">Back to List</a>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'My Rentals')

@section('content')
<div class="container py-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Rentals</h1>
        <a href="{{ route('rentals.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add New Rental
        </a>
    </div>

    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if($rentals->count() > 0)
        <!-- Rentals Grid -->
        <div class="row g-4">
            @foreach($rentals as $rental)
                <div class="col-lg-6 col-xl-4">
                    <!-- Rental Card -->
                    <div class="rental-card">
                        <!-- Rental Header -->
                        <div class="rental-header">
                            <div>
                                <h2 class="rental-name">
                                    @php
                                        $addressParts = array_filter([
                                            $rental->rental_line1 ?? '',
                                            $rental->rental_post_code ?? ''
                                        ]);
                                        $displayAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Rental Property';
                                    @endphp
                                    {{ Str::limit($displayAddress, 30) }}
                                </h2>
                                <div class="rental-id">
                                    ID: {{ $rental->rental_id }} | Created: {{ $rental->created_at->format('d M Y') }}
                                </div>
                            </div>
                        </div>

                        <!-- Rental Details -->
                        <div class="rental-details">
                            <!-- Property Address -->
                            <div class="detail-group">
                                <div class="detail-label">Property Address</div>
                                <div class="detail-value">
                                    @if($rental->rental_line1)
                                        <div>{{ $rental->rental_line1 }}</div>
                                    @endif
                                    @if($rental->rental_line2)
                                        <div>{{ $rental->rental_line2 }}</div>
                                    @endif
                                    @if($rental->rental_city)
                                        <div>{{ $rental->rental_city }}</div>
                                    @endif
                                    @if($rental->rental_post_code)
                                        <div><strong>{{ $rental->rental_post_code }}</strong></div>
                                    @endif
                                    @if(empty(array_filter([$rental->rental_line1, $rental->rental_line2, $rental->rental_city, $rental->rental_post_code])))
                                        <div class="empty-value">No address specified</div>
                                    @endif
                                </div>
                            </div>

                            <!-- Agent Information -->
                            @if($rental->agent_contact_name || $rental->agent_company_name || $rental->agent_line1)
                                <div class="detail-group">
                                    <div class="detail-label">Agent</div>
                                    <div class="detail-value">
                                        @if($rental->agent_contact_name)
                                            <div>{{ $rental->agent_contact_name }}</div>
                                        @endif
                                        @if($rental->agent_company_name)
                                            <div>{{ $rental->agent_company_name }}</div>
                                        @endif
                                        @if($rental->agent_contact_email)
                                            <div><small>{{ $rental->agent_contact_email }}</small></div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Landlord Information -->
                            @if($rental->landlord_contact_name || $rental->landlord_company_name || $rental->landlord_line1)
                                <div class="detail-group">
                                    <div class="detail-label">Landlord</div>
                                    <div class="detail-value">
                                        @if($rental->landlord_contact_name)
                                            <div>{{ $rental->landlord_contact_name }}</div>
                                        @endif
                                        @if($rental->landlord_company_name)
                                            <div>{{ $rental->landlord_company_name }}</div>
                                        @endif
                                        @if($rental->landlord_contact_email)
                                            <div><small>{{ $rental->landlord_contact_email }}</small></div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Lease Details -->
                            @if($rental->lease_type || $rental->lease_expiry_date || $rental->no_of_tenants || $rental->rental_type)
                                <div class="detail-group">
                                    <div class="detail-label">Lease Details</div>
                                    <div class="detail-value">
                                        @if($rental->lease_type)
                                            <div>Lease Type: {{ $rental->lease_type }}</div>
                                        @endif
                                        @if($rental->lease_expiry_date)
                                            <div>Expires: {{ \Carbon\Carbon::parse($rental->lease_expiry_date)->format('d M Y') }}</div>
                                        @endif
                                        @if($rental->no_of_tenants)
                                            <div>Tenants: {{ $rental->no_of_tenants }}</div>
                                        @endif
                                        @if($rental->rental_type)
                                            <div>Type: {{ $rental->rental_type }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Documents -->
                            @if($rental->uploadedFiles && $rental->uploadedFiles->count() > 0)
                                <div class="detail-group">
                                    <div class="detail-label">Documents ({{ $rental->uploadedFiles->count() }})</div>
                                    <div class="detail-value">
                                        @foreach($rental->uploadedFiles as $file)
                                            <div style="margin-bottom: 4px;">
                                                <i class="fas fa-file-alt me-1"></i>
                                                {{ Str::limit($file->original_name, 30) }}
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Action Buttons -->
                        <div class="rental-actions">
                            <a href="{{ route('rentals.show', $rental->rental_id) }}" class="btn-action btn-details">View</a>
                            <a href="{{ route('rentals.edit', $rental->rental_id) }}" class="btn-action btn-edit">Edit</a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    @else
        <!-- Empty State -->
        <div class="text-center py-5">
            <div class="mb-4">
                <i class="fas fa-home fa-3x text-muted"></i>
            </div>
            <h3 class="text-muted">No rentals yet</h3>
            <p class="text-muted mb-4">Get started by adding your first rental property.</p>
            <a href="{{ route('rentals.create') }}" class="btn btn-primary btn-lg">
                <i class="fas fa-plus"></i> Add Your First Rental
            </a>
        </div>
    @endif
</div>

<style>
    .rental-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .rental-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px solid #007bff;
    }

    .rental-name {
        font-size: 1.25rem;
        font-weight: 600;
        color: #007bff;
        margin: 0;
        line-height: 1.2;
    }

    .rental-id {
        color: #6c757d;
        font-size: 0.85rem;
        margin-top: 2px;
    }

    .rental-details {
        display: grid;
        grid-template-columns: 1fr;
        gap: 12px;
        margin-bottom: 15px;
        flex-grow: 1;
    }

    .detail-group {
        padding: 12px;
        background: #f8f9fa;
        border-radius: 4px;
    }

    .detail-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.75rem;
        text-transform: uppercase;
        margin-bottom: 5px;
        letter-spacing: 0.5px;
    }

    .detail-value {
        color: #212529;
        font-size: 0.9rem;
        line-height: 1.4;
    }

    .detail-value div {
        margin-bottom: 2px;
    }

    .detail-value div:last-child {
        margin-bottom: 0;
    }

    .rental-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
        margin-top: auto;
    }

    .btn-action {
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
        font-size: 0.875rem;
        border: none;
        cursor: pointer;
        flex: 1;
        text-align: center;
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

    .btn-details {
        background: #28a745;
        color: white;
    }

    .btn-details:hover {
        background: #1e7e34;
        color: white;
        text-decoration: none;
    }

    .empty-value {
        color: #6c757d;
        font-style: italic;
        font-size: 0.85rem;
    }

    @media (max-width: 768px) {
        .rental-details {
            grid-template-columns: 1fr;
        }

        .rental-name {
            font-size: 1.1rem;
        }
    }
</style>
@endsection

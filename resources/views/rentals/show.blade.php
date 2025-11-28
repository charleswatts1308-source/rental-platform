@extends('layouts.app')

@section('title', 'Rental Details')

@section('content')
<div class="container py-4">
    <h1>Rental Details</h1>

    <!-- Success Message -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Error Message -->
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

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
                    {{ $displayAddress }}
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
                    @if($rental->agent_line1)
                        <div>{{ $rental->agent_line1 }}</div>
                    @endif
                    @if($rental->agent_line2)
                        <div>{{ $rental->agent_line2 }}</div>
                    @endif
                    @if($rental->agent_city)
                        <div>{{ $rental->agent_city }}</div>
                    @endif
                    @if($rental->agent_post_code)
                        <div><strong>{{ $rental->agent_post_code }}</strong></div>
                    @endif
                    @if(empty(array_filter([$rental->agent_contact_name, $rental->agent_company_name, $rental->agent_contact_email, $rental->agent_line1])))
                        <div class="empty-value">No agent information</div>
                    @endif
                </div>
            </div>

            <!-- Landlord Information -->
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
                    @if($rental->landlord_line1)
                        <div>{{ $rental->landlord_line1 }}</div>
                    @endif
                    @if($rental->landlord_line2)
                        <div>{{ $rental->landlord_line2 }}</div>
                    @endif
                    @if($rental->landlord_city)
                        <div>{{ $rental->landlord_city }}</div>
                    @endif
                    @if($rental->landlord_post_code)
                        <div><strong>{{ $rental->landlord_post_code }}</strong></div>
                    @endif
                    @if(empty(array_filter([$rental->landlord_contact_name, $rental->landlord_company_name, $rental->landlord_contact_email, $rental->landlord_line1])))
                        <div class="empty-value">No landlord information</div>
                    @endif
                </div>
            </div>

            <!-- Lease Details -->
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
                        <div>Rental Type: {{ $rental->rental_type }}</div>
                    @endif
                    @if(empty(array_filter([$rental->lease_type, $rental->lease_expiry_date, $rental->no_of_tenants, $rental->rental_type])))
                        <div class="empty-value">No lease information</div>
                    @endif
                </div>
            </div>

            <!-- Service Requests -->
            <div class="detail-group">
                <div class="detail-label">Service Requests</div>
                <div class="detail-value">
                    <div>LL Status: {!! $rental->serv_req1_ll_status ? '<span class="checkmark">✓</span>' : '—' !!}</div>
                    <div>LL PU: {!! $rental->serv_req2_ll_pu ? '<span class="checkmark">✓</span>' : '—' !!}</div>
                </div>
            </div>

            <!-- Documents (File Upload Integration) -->
            @if($rental->uploadedFiles && $rental->uploadedFiles->count() > 0)
                <div class="detail-group">
                    <div class="detail-label">Documents</div>
                    <div class="detail-value">
                        @foreach($rental->uploadedFiles as $file)
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                <div>
                                    <i class="fas fa-file-pdf text-danger me-1"></i>
                                    {{ $file->original_name }}
                                    <small class="text-muted">({{ $file->humanSize }})</small>
                                </div>
                                <a href="{{ route('files.download', $file->id) }}" class="btn-action" style="font-size: 0.75rem; padding: 4px 8px;">
                                    Download
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- Action Buttons -->
        <div class="rental-actions">
            <a href="{{ route('rentals.edit', $rental->rental_id) }}" class="btn-action btn-edit">Edit</a>
            <a href="{{ route('rentals.index') }}" class="btn-action btn-back">Back to List</a>
        </div>
    </div>
</div>

<style>
    .rental-card {
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
        background: #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        font-size: 1.5rem;
        font-weight: 600;
        color: #007bff;
        margin: 0;
    }

    .rental-id {
        color: #6c757d;
        font-size: 0.9rem;
    }

    .rental-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .detail-group {
        padding: 10px;
        background: #f8f9fa;
        border-radius: 4px;
    }

    .detail-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.85rem;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .detail-value {
        color: #212529;
        font-size: 1rem;
    }

    .rental-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        padding-top: 15px;
        border-top: 1px solid #dee2e6;
    }

    .btn-action {
        padding: 8px 16px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: 500;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        font-size: 0.9rem;
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
        background: #545b62;
        color: white;
        text-decoration: none;
    }

    .checkmark {
        color: #28a745;
        font-weight: bold;
    }

    .empty-value {
        color: #6c757d;
        font-style: italic;
    }
</style>
@endsection

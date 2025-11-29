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

    <!-- Main Card -->
    <div class="card shadow-sm mb-4 border border-2">
        <!-- Card Header -->
        <div class="card-header bg-light">
            <h2 class="h5 mb-0 text-primary">
                @php
                    $addressParts = array_filter([
                        $rental->rental_line1 ?? '',
                        $rental->rental_post_code ?? ''
                    ]);
                    $displayAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Rental Property';
                @endphp
                {{ $displayAddress }}
            </h2>
            <small class="text-muted">
                ID: {{ $rental->rental_id }} | Created: {{ $rental->created_at->format('d M Y') }}
            </small>
        </div>

        <div class="card-body">
            <div class="row g-4">
                <!-- Property Address -->
                <div class="col-12 col-lg-6">
                    <div class="bg-light rounded p-3 h-100">
                        <h6 class="text-uppercase fw-bolder text-secondary mb-3">Property Address</h6>
                        <div>
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
                                <div class="text-muted fst-italic">No address specified</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Service Requests -->
                <div class="col-12 col-lg-6">
                    <div class="bg-light rounded p-3 h-100">
                        <h6 class="text-uppercase fw-bolder text-secondary mb-3">Service Requests</h6>
                        <div>
                            <div>LL Status: {!! $rental->serv_req1_ll_status ? '<span class="text-success fw-bold">&#10003;</span>' : '—' !!}</div>
                            <div>LL PU: {!! $rental->serv_req2_ll_pu ? '<span class="text-success fw-bold">&#10003;</span>' : '—' !!}</div>
                        </div>
                    </div>
                </div>

                <!-- Lease Details -->
                <div class="col-12 col-lg-6">
                    <div class="bg-light rounded p-3 h-100">
                        <h6 class="text-uppercase fw-bolder text-secondary mb-3">Lease Details</h6>
                        <div>
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
                                <div>Property Type: {{ $rental->rental_type }}</div>
                            @endif
                            @if(empty(array_filter([$rental->lease_type, $rental->lease_expiry_date, $rental->no_of_tenants, $rental->rental_type])))
                                <div class="text-muted fst-italic">No lease information</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Documents -->
                <div class="col-12 col-lg-6">
                    <div class="bg-light rounded p-3 h-100">
                        <h6 class="text-uppercase fw-bolder text-secondary mb-3">Rental / Tenancy Agreement</h6>
                        @if($rental->uploadedFiles && $rental->uploadedFiles->count() > 0)
                            @foreach($rental->uploadedFiles as $file)
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded">
                                    <div>
                                        <i class="fas fa-file-pdf text-danger me-1"></i>
                                        {{ $file->original_name }}
                                        <small class="text-muted">({{ $file->humanSize }})</small>
                                    </div>
                                    <a href="{{ route('files.download', $file->id) }}" class="btn btn-outline-primary btn-sm">
                                        Download
                                    </a>
                                </div>
                            @endforeach
                        @else
                            <div class="text-muted fst-italic">No documents uploaded</div>
                        @endif
                    </div>
                </div>

                <!-- Agent Information -->
                <div class="col-12 col-lg-6">
                    <div class="bg-light rounded p-3 h-100">
                        <h6 class="text-uppercase fw-bolder text-secondary mb-3">Agent</h6>
                        <div>
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
                                <div class="text-muted fst-italic">No agent information</div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Landlord Information -->
                <div class="col-12 col-lg-6">
                    <div class="bg-light rounded p-3 h-100">
                        <h6 class="text-uppercase fw-bolder text-secondary mb-3">Landlord</h6>
                        <div>
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
                                <div class="text-muted fst-italic">No landlord information</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card Footer with Actions -->
        <div class="card-footer">
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('rentals.edit', $rental->rental_id) }}" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit
                </a>
                <a href="{{ route('rentals.index') }}" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

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
                    <div class="card shadow-sm h-100 border border-2">
                        <!-- Card Header -->
                        <div class="card-header bg-light">
                            <h2 class="h6 mb-0 text-primary">
                                @php
                                    $addressParts = array_filter([
                                        $rental->rental_line1 ?? '',
                                        $rental->rental_post_code ?? ''
                                    ]);
                                    $displayAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Rental Property';
                                @endphp
                                {{ Str::limit($displayAddress, 30) }}
                            </h2>
                            <small class="text-muted">
                                ID: {{ $rental->rental_id }} | Created: {{ $rental->created_at->format('d M Y') }}
                            </small>
                        </div>

                        <!-- Card Body -->
                        <div class="card-body">
                            <div class="bg-light rounded p-3">
                                <h6 class="text-uppercase fw-bolder text-secondary mb-2">Property Address</h6>
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

                        <!-- Card Footer -->
                        <div class="card-footer">
                            <a href="{{ route('rentals.edit', $rental->rental_id) }}" class="btn btn-primary w-100">Edit</a>
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
@endsection

@extends('layouts.app')

@section('title', 'Edit Rental')

@section('content')
<div class="container py-4">
    <h1>Edit Rental</h1>

    <!-- Validation Errors -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <h6>Please correct the following errors:</h6>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('rentals.update', $rental->rental_id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Main Card -->
        <div class="card shadow-sm mb-4">
            <!-- Card Header -->
            <div class="card-header bg-primary text-white">
                <h2 class="h5 mb-0">
                    @php
                        $addressParts = array_filter([
                            $rental->rental_line1 ?? '',
                            $rental->rental_post_code ?? ''
                        ]);
                        $displayAddress = !empty($addressParts) ? implode(', ', $addressParts) : 'Rental Property';
                    @endphp
                    {{ $displayAddress }}
                </h2>
                <small class="opacity-75">
                    ID: {{ $rental->rental_id }} | Created: {{ $rental->created_at->format('d M Y') }}
                </small>
            </div>

            <div class="card-body">
                <div class="row g-4">
                    <!-- Property Address -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-light rounded p-3 h-100">
                            <h6 class="text-uppercase fw-bolder text-secondary mb-3">Property Address</h6>
                            <div class="mb-3">
                                <label for="rental_post_code" class="form-label">Postcode</label>
                                <input type="text" class="form-control" id="rental_post_code" name="rental_post_code"
                                       value="{{ old('rental_post_code', $rental->rental_post_code) }}" required>
                                <small id="rental_post_code_feedback" class="form-text"></small>
                            </div>
                            <div class="mb-3">
                                <label for="rental_line1" class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" id="rental_line1" name="rental_line1"
                                       value="{{ old('rental_line1', $rental->rental_line1) }}" required>
                            </div>
                            <div class="mb-3">
                                <label for="rental_line2" class="form-label">Address Line 2 (optional)</label>
                                <input type="text" class="form-control" id="rental_line2" name="rental_line2"
                                       value="{{ old('rental_line2', $rental->rental_line2) }}">
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="rental_city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="rental_city" name="rental_city"
                                               value="{{ old('rental_city', $rental->rental_city) }}" required>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="rental_county" class="form-label">County (optional)</label>
                                        <input type="text" class="form-control" id="rental_county" name="rental_county"
                                               value="{{ old('rental_county', $rental->rental_county) }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Service Requests -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-light rounded p-3 h-100">
                            <h6 class="text-uppercase fw-bolder text-secondary mb-3">Service Requests</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="serv_req1_ll_status" name="serv_req1_ll_status"
                                       value="1" {{ old('serv_req1_ll_status', $rental->serv_req1_ll_status) ? 'checked' : '' }}>
                                <label class="form-check-label" for="serv_req1_ll_status">
                                    LL Status
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="serv_req2_ll_pu" name="serv_req2_ll_pu"
                                       value="1" {{ old('serv_req2_ll_pu', $rental->serv_req2_ll_pu) ? 'checked' : '' }}>
                                <label class="form-check-label" for="serv_req2_ll_pu">
                                    LL PU
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Lease Details -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-light rounded p-3 h-100">
                            <h6 class="text-uppercase fw-bolder text-secondary mb-3">Lease Details</h6>
                            <div class="mb-3">
                                <label for="lease_type" class="form-label">Lease Type</label>
                                <select class="form-select" id="lease_type" name="lease_type">
                                    <option value="">Select lease type...</option>
                                    <option value="Assured Shorthold Tenancy" {{ old('lease_type', $rental->lease_type) == 'Assured Shorthold Tenancy' ? 'selected' : '' }}>Assured Shorthold Tenancy</option>
                                    <option value="Assured Tenancy" {{ old('lease_type', $rental->lease_type) == 'Assured Tenancy' ? 'selected' : '' }}>Assured Tenancy</option>
                                    <option value="Regulated Tenancy" {{ old('lease_type', $rental->lease_type) == 'Regulated Tenancy' ? 'selected' : '' }}>Regulated Tenancy</option>
                                    <option value="Licence Agreement" {{ old('lease_type', $rental->lease_type) == 'Licence Agreement' ? 'selected' : '' }}>Licence Agreement</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="lease_expiry_date" class="form-label">Lease Expiry Date</label>
                                <input type="date" class="form-control" id="lease_expiry_date" name="lease_expiry_date"
                                       value="{{ old('lease_expiry_date', $rental->lease_expiry_date) }}">
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="no_of_tenants" class="form-label">Number of tenants</label>
                                        <input type="text" class="form-control" id="no_of_tenants" name="no_of_tenants"
                                               value="{{ old('no_of_tenants', $rental->no_of_tenants) }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="rental_type" class="form-label">Property Type</label>
                                        <select class="form-select" id="rental_type" name="rental_type">
                                            <option value="">Select property type...</option>
                                            <option value="House" {{ old('rental_type', $rental->rental_type) == 'House' ? 'selected' : '' }}>House</option>
                                            <option value="Flat" {{ old('rental_type', $rental->rental_type) == 'Flat' ? 'selected' : '' }}>Flat</option>
                                            <option value="Studio" {{ old('rental_type', $rental->rental_type) == 'Studio' ? 'selected' : '' }}>Studio</option>
                                            <option value="Room" {{ old('rental_type', $rental->rental_type) == 'Room' ? 'selected' : '' }}>Room</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rental / Tenancy Agreement -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-light rounded p-3 h-100">
                            <h6 class="text-uppercase fw-bolder text-secondary mb-3">Rental / Tenancy Agreement</h6>
                            <!-- Existing Files -->
                            @if($rental->uploadedFiles && $rental->uploadedFiles->count() > 0)
                                <div class="mb-3">
                                    <p class="form-label">Current Documents:</p>
                                    @foreach($rental->uploadedFiles as $file)
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-white rounded">
                                            <div>
                                                <i class="fas fa-file-pdf text-danger me-1"></i>
                                                {{ $file->original_name }}
                                                <small class="text-muted">({{ $file->humanSize }})</small>
                                            </div>
                                            <div>
                                                <a href="{{ route('files.download', $file->id) }}" class="btn btn-outline-primary btn-sm">
                                                    Download
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <!-- Upload New File -->
                            <div class="mb-3">
                                <label for="rental_agreement" class="form-label">Upload New Document (PDF, Images, Word, Excel)</label>
                                <input type="file" class="form-control" id="rental_agreement" name="rental_agreement"
                                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                <small class="form-text text-muted">Maximum file size: 2MB. File will be uploaded when you save changes.</small>
                            </div>
                        </div>
                    </div>

                    <!-- Agent Information -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-light rounded p-3 h-100">
                            <h6 class="text-uppercase fw-bolder text-secondary mb-3">Agent</h6>
                            <div class="mb-3">
                                <label for="agent_post_code" class="form-label">Postcode</label>
                                <input type="text" class="form-control" id="agent_post_code" name="agent_post_code"
                                       value="{{ old('agent_post_code', $rental->agent_post_code) }}">
                                <small id="agent_post_code_feedback" class="form-text"></small>
                            </div>
                            <div class="mb-3">
                                <label for="agent_line1" class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" id="agent_line1" name="agent_line1"
                                       value="{{ old('agent_line1', $rental->agent_line1) }}">
                            </div>
                            <div class="mb-3">
                                <label for="agent_line2" class="form-label">Address Line 2 (optional)</label>
                                <input type="text" class="form-control" id="agent_line2" name="agent_line2"
                                       value="{{ old('agent_line2', $rental->agent_line2) }}">
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="agent_city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="agent_city" name="agent_city"
                                               value="{{ old('agent_city', $rental->agent_city) }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="agent_county" class="form-label">County (optional)</label>
                                        <input type="text" class="form-control" id="agent_county" name="agent_county"
                                               value="{{ old('agent_county', $rental->agent_county) }}">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="agent_contact_name" class="form-label">Contact Name</label>
                                <input type="text" class="form-control" id="agent_contact_name" name="agent_contact_name"
                                       value="{{ old('agent_contact_name', $rental->agent_contact_name) }}">
                            </div>
                            <div class="mb-3">
                                <label for="agent_contact_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="agent_contact_email" name="agent_contact_email"
                                       value="{{ old('agent_contact_email', $rental->agent_contact_email) }}">
                            </div>
                            <div class="mb-3">
                                <label for="agent_company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="agent_company_name" name="agent_company_name"
                                       value="{{ old('agent_company_name', $rental->agent_company_name) }}">
                            </div>
                        </div>
                    </div>

                    <!-- Landlord Information -->
                    <div class="col-12 col-lg-6">
                        <div class="bg-light rounded p-3 h-100">
                            <h6 class="text-uppercase fw-bolder text-secondary mb-3">Landlord</h6>
                            <div class="mb-3">
                                <label for="landlord_post_code" class="form-label">Postcode</label>
                                <input type="text" class="form-control" id="landlord_post_code" name="landlord_post_code"
                                       value="{{ old('landlord_post_code', $rental->landlord_post_code) }}">
                                <small id="landlord_post_code_feedback" class="form-text"></small>
                            </div>
                            <div class="mb-3">
                                <label for="landlord_line1" class="form-label">Address Line 1</label>
                                <input type="text" class="form-control" id="landlord_line1" name="landlord_line1"
                                       value="{{ old('landlord_line1', $rental->landlord_line1) }}">
                            </div>
                            <div class="mb-3">
                                <label for="landlord_line2" class="form-label">Address Line 2 (optional)</label>
                                <input type="text" class="form-control" id="landlord_line2" name="landlord_line2"
                                       value="{{ old('landlord_line2', $rental->landlord_line2) }}">
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="landlord_city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="landlord_city" name="landlord_city"
                                               value="{{ old('landlord_city', $rental->landlord_city) }}">
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="mb-3">
                                        <label for="landlord_county" class="form-label">County (optional)</label>
                                        <input type="text" class="form-control" id="landlord_county" name="landlord_county"
                                               value="{{ old('landlord_county', $rental->landlord_county) }}">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="landlord_contact_name" class="form-label">Contact Name</label>
                                <input type="text" class="form-control" id="landlord_contact_name" name="landlord_contact_name"
                                       value="{{ old('landlord_contact_name', $rental->landlord_contact_name) }}">
                            </div>
                            <div class="mb-3">
                                <label for="landlord_contact_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="landlord_contact_email" name="landlord_contact_email"
                                       value="{{ old('landlord_contact_email', $rental->landlord_contact_email) }}">
                            </div>
                            <div class="mb-3">
                                <label for="landlord_company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="landlord_company_name" name="landlord_company_name"
                                       value="{{ old('landlord_company_name', $rental->landlord_company_name) }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Footer with Actions -->
            <div class="card-footer">
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="{{ route('rentals.index') }}" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Format UK postcode to conventional format (uppercase with space before last 3 chars)
        function formatPostcode(postcode) {
            // Remove all spaces and convert to uppercase
            const cleanPostcode = postcode.replace(/\s/g, '').toUpperCase();

            // Basic UK postcode validation
            const postcodeRegex = /^[A-Z]{1,2}\d{1,2}[A-Z]?\d[A-Z]{2}$/;
            if (!postcodeRegex.test(cleanPostcode)) {
                return postcode; // Return original if invalid format
            }

            // Insert space before last 3 characters
            return cleanPostcode.slice(0, -3) + ' ' + cleanPostcode.slice(-3);
        }

        // Postcode lookup functionality
        async function lookupPostcode(postcode, postcodeInputId, cityInputId, countyInputId) {
            const feedbackElement = document.getElementById(postcodeInputId + '_feedback');

            // Clear previous feedback
            if (feedbackElement) {
                feedbackElement.textContent = '';
                feedbackElement.className = 'form-text';
            }

            // Remove spaces and convert to uppercase for validation
            const cleanPostcode = postcode.replace(/\s/g, '').toUpperCase();

            // Basic UK postcode validation
            const postcodeRegex = /^[A-Z]{1,2}\d{1,2}[A-Z]?\d[A-Z]{2}$/;
            if (!postcodeRegex.test(cleanPostcode)) {
                if (feedbackElement) {
                    feedbackElement.textContent = 'Invalid UK postcode format';
                    feedbackElement.className = 'form-text text-danger';
                }
                return;
            }

            // Format the postcode in the input field
            const postcodeInput = document.getElementById(postcodeInputId);
            if (postcodeInput) {
                const formattedPostcode = formatPostcode(cleanPostcode);
                postcodeInput.value = formattedPostcode;
            }

            // Show loading message
            if (feedbackElement) {
                feedbackElement.textContent = 'Looking up postcode...';
                feedbackElement.className = 'form-text text-muted';
            }

            try {
                const response = await fetch(`https://api.postcodes.io/postcodes/${encodeURIComponent(cleanPostcode)}`);
                const data = await response.json();

                if (data.status === 200 && data.result) {
                    const cityInput = document.getElementById(cityInputId);
                    const countyInput = document.getElementById(countyInputId);

                    // Populate city (admin_district is typically the city/town)
                    if (cityInput && data.result.admin_district) {
                        cityInput.value = data.result.admin_district;
                    }

                    // Populate county (admin_county)
                    if (countyInput && data.result.admin_county) {
                        countyInput.value = data.result.admin_county;
                    }

                    // Show success message
                    if (feedbackElement) {
                        feedbackElement.textContent = 'Postcode found';
                        feedbackElement.className = 'form-text text-success';
                        // Clear success message after 3 seconds
                        setTimeout(() => {
                            feedbackElement.textContent = '';
                        }, 3000);
                    }
                } else {
                    // Postcode not found
                    if (feedbackElement) {
                        feedbackElement.textContent = 'Postcode not found - please enter city/county manually';
                        feedbackElement.className = 'form-text text-danger';
                    }
                }
            } catch (error) {
                // Network or API error
                if (feedbackElement) {
                    feedbackElement.textContent = 'Unable to lookup postcode - please enter city/county manually';
                    feedbackElement.className = 'form-text text-danger';
                }
                console.log('Postcode lookup failed:', error);
            }
        }

        // Add postcode lookup to all three postcode fields
        const postcodeFields = [
            { postcode: 'rental_post_code', city: 'rental_city', county: 'rental_county' },
            { postcode: 'agent_post_code', city: 'agent_city', county: 'agent_county' },
            { postcode: 'landlord_post_code', city: 'landlord_city', county: 'landlord_county' }
        ];

        postcodeFields.forEach(function(fields) {
            const postcodeInput = document.getElementById(fields.postcode);
            if (postcodeInput) {
                postcodeInput.addEventListener('blur', function() {
                    const postcode = this.value.trim();
                    if (postcode) {
                        lookupPostcode(postcode, fields.postcode, fields.city, fields.county);
                    }
                });
            }
        });
    });
</script>
@endsection

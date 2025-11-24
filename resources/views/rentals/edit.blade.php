@extends('layouts.app')

@section('title', 'Edit Rental Profile')

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

    .form-section {
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

    .detail-group {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
        margin-bottom: 15px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        font-weight: 600;
        color: #495057;
        font-size: 0.9rem;
        margin-bottom: 5px;
        display: block;
    }

    .required-indicator {
        color: #dc3545;
        margin-right: 4px;
    }

    .form-control {
        border-radius: 4px;
        border: 1px solid #ced4da;
        width: 100%;
        padding: 8px 12px;
        font-size: 0.9rem;
    }

    .form-control:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        outline: none;
    }

    .form-check {
        padding-left: 1.5rem;
        margin-bottom: 10px;
    }

    .form-check-input {
        margin-top: 0.3rem;
    }

    .form-check-label {
        font-weight: 500;
        color: #495057;
    }

    .rental-actions {
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

    .btn-save {
        background: #28a745;
        color: white;
    }

    .btn-save:hover {
        background: #218838;
        color: white;
    }

    .btn-cancel {
        background: #6c757d;
        color: white;
    }

    .btn-cancel:hover {
        background: #5a6268;
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

    .text-danger {
        font-size: 0.85rem;
        margin-top: 5px;
        display: block;
        color: #dc3545;
    }

    .validation-errors {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
        padding: 15px;
        border-radius: 4px;
        margin-bottom: 20px;
    }

    .validation-errors ul {
        margin-bottom: 0;
        padding-left: 20px;
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

<h1>Edit Rental Profile</h1>

<div class="rental-card">
    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('rentals.update', $rental) }}">
        @csrf
        @method('PUT')

        @if ($errors->any())
            <div class="validation-errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Hidden Fields -->
        <input type="hidden" name="user_id" value="{{ $rental->user_id }}">
        <input type="hidden" name="rental_id" value="{{ $rental->rental_id }}">
        <input type="hidden" name="date_created" value="{{ $rental->date_created }}">

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
            <div class="rental-id" style="color: #6c757d; font-size: 0.9rem;">
                ID: {{ $rental->rental_id }} | Created: {{ $rental->date_created ? $rental->date_created->format('d M Y') : 'N/A' }}
            </div>
        </div>

        <!-- Service Requests Section -->
        <div class="form-section">
            <h5 class="section-header">Service Requests</h5>
            <div class="detail-group">
                <div class="form-grid">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="serv_req1_ll_status" id="serv_req1_ll_status" value="1" {{ $rental->serv_req1_ll_status ? 'checked' : '' }}>
                        <label for="serv_req1_ll_status" class="form-check-label">LL Status</label>
                        @error('serv_req1_ll_status')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="serv_req2_ll_pu" id="serv_req2_ll_pu" value="1" {{ $rental->serv_req2_ll_pu ? 'checked' : '' }}>
                        <label for="serv_req2_ll_pu" class="form-check-label">LL PU</label>
                        @error('serv_req2_ll_pu')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Property Address Section -->
        <div class="form-section">
            <h5 class="section-header">Property Address</h5>
            <div class="detail-group">
                <div class="form-group">
                    <label for="rental_line1" class="control-label">
                        <span class="required-indicator">*</span>Address Line 1
                    </label>
                    <input type="text" name="rental_line1" id="rental_line1" class="form-control" value="{{ old('rental_line1', $rental->rental_line1) }}" required>
                    @error('rental_line1')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="rental_line2" class="control-label">Address Line 2</label>
                    <input type="text" name="rental_line2" id="rental_line2" class="form-control" value="{{ old('rental_line2', $rental->rental_line2) }}">
                    @error('rental_line2')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="rental_city" class="control-label">
                            <span class="required-indicator">*</span>City
                        </label>
                        <input type="text" name="rental_city" id="rental_city" class="form-control" value="{{ old('rental_city', $rental->rental_city) }}" required>
                        @error('rental_city')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="rental_post_code" class="control-label">
                            <span class="required-indicator">*</span>Post Code
                        </label>
                        <div class="input-group" style="display: flex;">
                            <input type="text" name="rental_post_code" id="rental_post_code" class="form-control" value="{{ old('rental_post_code', $rental->rental_post_code) }}" required style="margin-right: 5px;">
                            <button type="button" class="btn btn-primary" onclick="lookupPostcode('rental', event)" style="background: #007bff; color: white; border: 1px solid #007bff; padding: 8px 12px; border-radius: 4px;">Find</button>
                        </div>
                        <span id="rental-status" class="lookup-status"></span>
                        @error('rental_post_code')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent Section -->
        <div class="form-section">
            <h5 class="section-header">Agent</h5>
            <div class="detail-group">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="agent_contact_name" class="control-label">Contact Name</label>
                        <input type="text" name="agent_contact_name" id="agent_contact_name" class="form-control" value="{{ old('agent_contact_name', $rental->agent_contact_name) }}">
                        @error('agent_contact_name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="agent_contact_email" class="control-label">Contact Email</label>
                        <input type="email" name="agent_contact_email" id="agent_contact_email" class="form-control" value="{{ old('agent_contact_email', $rental->agent_contact_email) }}">
                        @error('agent_contact_email')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="agent_company_name" class="control-label">Company Name</label>
                        <input type="text" name="agent_company_name" id="agent_company_name" class="form-control" value="{{ old('agent_company_name', $rental->agent_company_name) }}">
                        @error('agent_company_name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="agent_company_email" class="control-label">Company Email</label>
                        <input type="email" name="agent_company_email" id="agent_company_email" class="form-control" value="{{ old('agent_company_email', $rental->agent_company_email) }}">
                        @error('agent_company_email')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="agent_line1" class="control-label">Address Line 1</label>
                    <input type="text" name="agent_line1" id="agent_line1" class="form-control" value="{{ old('agent_line1', $rental->agent_line1) }}">
                    @error('agent_line1')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="agent_line2" class="control-label">Address Line 2</label>
                    <input type="text" name="agent_line2" id="agent_line2" class="form-control" value="{{ old('agent_line2', $rental->agent_line2) }}">
                    @error('agent_line2')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="agent_city" class="control-label">City</label>
                        <input type="text" name="agent_city" id="agent_city" class="form-control" value="{{ old('agent_city', $rental->agent_city) }}">
                        @error('agent_city')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="agent_post_code" class="control-label">Post Code</label>
                        <input type="text" name="agent_post_code" id="agent_post_code" class="form-control" value="{{ old('agent_post_code', $rental->agent_post_code) }}">
                        @error('agent_post_code')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Landlord Section -->
        <div class="form-section">
            <h5 class="section-header">Landlord</h5>
            <div class="detail-group">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="landlord_contact_name" class="control-label">Contact Name</label>
                        <input type="text" name="landlord_contact_name" id="landlord_contact_name" class="form-control" value="{{ old('landlord_contact_name', $rental->landlord_contact_name) }}">
                        @error('landlord_contact_name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="landlord_contact_email" class="control-label">Contact Email</label>
                        <input type="email" name="landlord_contact_email" id="landlord_contact_email" class="form-control" value="{{ old('landlord_contact_email', $rental->landlord_contact_email) }}">
                        @error('landlord_contact_email')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="landlord_company_name" class="control-label">Company Name</label>
                        <input type="text" name="landlord_company_name" id="landlord_company_name" class="form-control" value="{{ old('landlord_company_name', $rental->landlord_company_name) }}">
                        @error('landlord_company_name')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="landlord_company_email" class="control-label">Company Email</label>
                        <input type="email" name="landlord_company_email" id="landlord_company_email" class="form-control" value="{{ old('landlord_company_email', $rental->landlord_company_email) }}">
                        @error('landlord_company_email')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-group">
                    <label for="landlord_line1" class="control-label">Address Line 1</label>
                    <input type="text" name="landlord_line1" id="landlord_line1" class="form-control" value="{{ old('landlord_line1', $rental->landlord_line1) }}">
                    @error('landlord_line1')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label for="landlord_line2" class="control-label">Address Line 2</label>
                    <input type="text" name="landlord_line2" id="landlord_line2" class="form-control" value="{{ old('landlord_line2', $rental->landlord_line2) }}">
                    @error('landlord_line2')<span class="text-danger">{{ $message }}</span>@enderror
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="landlord_city" class="control-label">City</label>
                        <input type="text" name="landlord_city" id="landlord_city" class="form-control" value="{{ old('landlord_city', $rental->landlord_city) }}">
                        @error('landlord_city')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="landlord_post_code" class="control-label">Post Code</label>
                        <input type="text" name="landlord_post_code" id="landlord_post_code" class="form-control" value="{{ old('landlord_post_code', $rental->landlord_post_code) }}">
                        @error('landlord_post_code')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Lease Details Section -->
        <div class="form-section">
            <h5 class="section-header">Lease Details</h5>
            <div class="detail-group">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="lease_type" class="control-label">Lease Type</label>
                        <input type="text" name="lease_type" id="lease_type" class="form-control" value="{{ old('lease_type', $rental->lease_type) }}">
                        @error('lease_type')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="lease_expiry_date" class="control-label">Lease Expiry Date</label>
                        <input type="date" name="lease_expiry_date" id="lease_expiry_date" class="form-control" value="{{ old('lease_expiry_date', $rental->lease_expiry_date) }}">
                        @error('lease_expiry_date')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="no_of_tenants" class="control-label">Number of Tenants</label>
                        <input type="text" name="no_of_tenants" id="no_of_tenants" class="form-control" value="{{ old('no_of_tenants', $rental->no_of_tenants) }}">
                        @error('no_of_tenants')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label for="rental_type" class="control-label">Rental Type</label>
                        <input type="text" name="rental_type" id="rental_type" class="form-control" value="{{ old('rental_type', $rental->rental_type) }}">
                        @error('rental_type')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="rental-actions">
            <button type="submit" class="btn-action btn-save">Update Profile</button>
            <a href="{{ route('rentals.show', $rental) }}" class="btn-action btn-cancel">Cancel</a>
        </div>
    </form>

    <!-- Delete form outside the edit form -->
    <div style="margin-top: 10px;">
        <form method="POST" action="{{ route('rentals.destroy', $rental) }}" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this rental?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn-action btn-delete">Delete</button>
        </form>
    </div>
</div>

<script>
function lookupPostcode(type, event) {
    const postcodeField = document.getElementById(type === 'rental' ? 'rental_post_code' :
                                                type === 'agent' ? 'agent_post_code' : 'landlord_post_code');
    const cityField = document.getElementById(type === 'rental' ? 'rental_city' :
                                             type === 'agent' ? 'agent_city' : 'landlord_city');
    const statusSpan = document.getElementById(type + '-status');
    const button = event.target;
    const postcode = postcodeField.value.trim().toUpperCase();

    if (!postcode) {
        showStatus(statusSpan, 'Please enter a postcode', 'error');
        return;
    }

    // Basic UK postcode validation
    const postcodeRegex = /^[A-Z]{1,2}[0-9R][0-9A-Z]?\s?[0-9][A-Z]{2}$/;
    if (!postcodeRegex.test(postcode)) {
        showStatus(statusSpan, 'Invalid UK postcode format', 'error');
        return;
    }

    // Set loading state
    button.disabled = true;
    button.textContent = 'Finding...';
    clearStatus(statusSpan);

    // Call Postcodes.io API
    fetch(`https://api.postcodes.io/postcodes/${encodeURIComponent(postcode)}`)
        .then(response => {
            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Postcode not found');
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 200 && data.result) {
                // Update the fields
                postcodeField.value = data.result.postcode; // Formatted postcode
                cityField.value = data.result.admin_district || data.result.admin_ward || '';
                showStatus(statusSpan, '✓ Address found', 'success');
            } else {
                throw new Error('Invalid response from postcode service');
            }
        })
        .catch(error => {
            console.error('Postcode lookup error:', error);
            showStatus(statusSpan, error.message || 'Unable to find postcode', 'error');
        })
        .finally(() => {
            // Reset button state
            button.disabled = false;
            button.textContent = 'Find';
        });
}

function showStatus(element, message, type) {
    element.textContent = message;
    element.className = 'lookup-status ' + type;
    if (type === 'success') {
        element.style.color = '#28a745';
    } else if (type === 'error') {
        element.style.color = '#dc3545';
    }
}

function clearStatus(element) {
    element.textContent = '';
    element.className = 'lookup-status';
}
</script>
@endsection

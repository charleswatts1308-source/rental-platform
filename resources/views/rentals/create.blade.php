@extends('layouts.app')

@section('title', 'Create Rental')

@section('content')
<div class="container py-4">
    <h1>Create Rental</h1>

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

    <form method="POST" action="{{ route('rentals.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="rental-card">
            <!-- Rental Header -->
            <div class="rental-header">
                <div>
                    <h2 class="rental-name">New Rental Property</h2>
                    <div class="rental-id">
                        Create a new rental property profile
                    </div>
                </div>
            </div>

            <!-- Rental Details Form -->
            <div class="rental-details">
                <!-- Property Address -->
                <div class="detail-group">
                    <div class="detail-label">Property Address</div>
                    <div class="detail-value">
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="rental_line1" name="rental_line1"
                                       value="{{ old('rental_line1') }}" required>
                                <label for="rental_line1">Address Line 1</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="rental_line2" name="rental_line2"
                                       value="{{ old('rental_line2') }}">
                                <label for="rental_line2">Address Line 2 (optional)</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <div class="floating-label">
                                    <input type="text" class="form-control form-control-sm" id="rental_city" name="rental_city"
                                           value="{{ old('rental_city') }}" required>
                                    <label for="rental_city">City</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="floating-label">
                                    <input type="text" class="form-control form-control-sm" id="rental_post_code" name="rental_post_code"
                                           value="{{ old('rental_post_code') }}" required>
                                    <label for="rental_post_code">Postcode</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Agent Information -->
                <div class="detail-group">
                    <div class="detail-label">Agent</div>
                    <div class="detail-value">
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="agent_contact_name" name="agent_contact_name"
                                       value="{{ old('agent_contact_name') }}">
                                <label for="agent_contact_name">Contact Name</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="agent_company_name" name="agent_company_name"
                                       value="{{ old('agent_company_name') }}">
                                <label for="agent_company_name">Company Name</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="email" class="form-control form-control-sm" id="agent_contact_email" name="agent_contact_email"
                                       value="{{ old('agent_contact_email') }}">
                                <label for="agent_contact_email">Email</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="agent_line1" name="agent_line1"
                                       value="{{ old('agent_line1') }}">
                                <label for="agent_line1">Address Line 1</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <div class="floating-label">
                                    <input type="text" class="form-control form-control-sm" id="agent_city" name="agent_city"
                                           value="{{ old('agent_city') }}">
                                    <label for="agent_city">City</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="floating-label">
                                    <input type="text" class="form-control form-control-sm" id="agent_post_code" name="agent_post_code"
                                           value="{{ old('agent_post_code') }}">
                                    <label for="agent_post_code">Postcode</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Landlord Information -->
                <div class="detail-group">
                    <div class="detail-label">Landlord</div>
                    <div class="detail-value">
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="landlord_contact_name" name="landlord_contact_name"
                                       value="{{ old('landlord_contact_name') }}">
                                <label for="landlord_contact_name">Contact Name</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="landlord_company_name" name="landlord_company_name"
                                       value="{{ old('landlord_company_name') }}">
                                <label for="landlord_company_name">Company Name</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="email" class="form-control form-control-sm" id="landlord_contact_email" name="landlord_contact_email"
                                       value="{{ old('landlord_contact_email') }}">
                                <label for="landlord_contact_email">Email</label>
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="text" class="form-control form-control-sm" id="landlord_line1" name="landlord_line1"
                                       value="{{ old('landlord_line1') }}">
                                <label for="landlord_line1">Address Line 1</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <div class="floating-label">
                                    <input type="text" class="form-control form-control-sm" id="landlord_city" name="landlord_city"
                                           value="{{ old('landlord_city') }}">
                                    <label for="landlord_city">City</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <div class="floating-label">
                                    <input type="text" class="form-control form-control-sm" id="landlord_post_code" name="landlord_post_code"
                                           value="{{ old('landlord_post_code') }}">
                                    <label for="landlord_post_code">Postcode</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Lease Details -->
                <div class="detail-group">
                    <div class="detail-label">Lease Details</div>
                    <div class="detail-value">
                        <div class="mb-2">
                            <select class="form-control form-control-sm" id="lease_type" name="lease_type">
                                <option value="">Select lease type...</option>
                                <option value="Assured Shorthold Tenancy" {{ old('lease_type') == 'Assured Shorthold Tenancy' ? 'selected' : '' }}>Assured Shorthold Tenancy</option>
                                <option value="Assured Tenancy" {{ old('lease_type') == 'Assured Tenancy' ? 'selected' : '' }}>Assured Tenancy</option>
                                <option value="Regulated Tenancy" {{ old('lease_type') == 'Regulated Tenancy' ? 'selected' : '' }}>Regulated Tenancy</option>
                                <option value="Licence Agreement" {{ old('lease_type') == 'Licence Agreement' ? 'selected' : '' }}>Licence Agreement</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <div class="floating-label">
                                <input type="date" class="form-control form-control-sm" id="lease_expiry_date" name="lease_expiry_date"
                                       value="{{ old('lease_expiry_date') }}">
                                <label for="lease_expiry_date">Lease expiry date</label>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-2">
                                <div class="floating-label">
                                    <input type="text" class="form-control form-control-sm" id="no_of_tenants" name="no_of_tenants"
                                           value="{{ old('no_of_tenants') }}">
                                    <label for="no_of_tenants">Number of tenants</label>
                                </div>
                            </div>
                            <div class="col-6 mb-2">
                                <select class="form-control form-control-sm" id="rental_type" name="rental_type">
                                    <option value="">Select property type...</option>
                                    <option value="House" {{ old('rental_type') == 'House' ? 'selected' : '' }}>House</option>
                                    <option value="Flat" {{ old('rental_type') == 'Flat' ? 'selected' : '' }}>Flat</option>
                                    <option value="Studio" {{ old('rental_type') == 'Studio' ? 'selected' : '' }}>Studio</option>
                                    <option value="Room" {{ old('rental_type') == 'Room' ? 'selected' : '' }}>Room</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Requests -->
                <div class="detail-group">
                    <div class="detail-label">Service Requests</div>
                    <div class="detail-value">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="serv_req1_ll_status" name="serv_req1_ll_status"
                                   value="1" {{ old('serv_req1_ll_status') ? 'checked' : '' }}>
                            <label class="form-check-label" for="serv_req1_ll_status">
                                LL Status
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="serv_req2_ll_pu" name="serv_req2_ll_pu"
                                   value="1" {{ old('serv_req2_ll_pu') ? 'checked' : '' }}>
                            <label class="form-check-label" for="serv_req2_ll_pu">
                                LL PU
                            </label>
                        </div>
                    </div>
                </div>

                <!-- File Upload Section -->
                <div class="detail-group">
                    <div class="detail-label">Documents</div>
                    <div class="detail-value">
                        <div>
                            <label for="rental_agreement" class="form-label-small">Upload Document (PDF, Images, Word, Excel)</label>
                            <div class="d-flex gap-2 align-items-end">
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control form-control-sm" id="rental_agreement" name="rental_agreement"
                                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                                </div>
                            </div>
                            <small class="text-muted">Maximum file size: 2MB. File will be uploaded when you create the rental.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="rental-actions">
                <button type="submit" class="btn-action btn-save">
                    <i class="fas fa-plus"></i> Create Rental
                </button>
                <a href="{{ route('rentals.index') }}" class="btn-action btn-back">
                    <i class="fas fa-arrow-left"></i> Back to List
                </a>
            </div>
        </div>
    </form>
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
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .detail-group {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
    }

    .detail-label {
        font-weight: 600;
        color: #495057;
        font-size: 0.85rem;
        text-transform: uppercase;
        margin-bottom: 10px;
    }

    .detail-value {
        color: #212529;
    }

    .form-label-small {
        font-size: 0.8rem;
        color: #495057;
        font-weight: 500;
        margin-bottom: 2px;
    }

    .form-control-sm {
        font-size: 0.875rem;
    }

    /* Floating Label Styles */
    .floating-label {
        position: relative;
        margin-bottom: 10px;
    }

    .floating-label input {
        width: 100%;
        padding: 1.2rem 0.75rem 0.3rem 0.75rem;
        border: 2px solid #ced4da;
        border-radius: 6px;
        font-size: 0.875rem;
        background: white;
        color: #495057;
        transition: all 0.2s ease-in-out;
        outline: none;
    }

    .floating-label input::placeholder {
        opacity: 0;
    }

    .floating-label input:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.15);
    }

    .floating-label label {
        position: absolute;
        top: 1rem;
        left: 0.75rem;
        font-size: 0.875rem;
        color: #6c757d;
        pointer-events: none;
        transition: all 0.2s ease-in-out;
        background: white;
        padding: 0 6px;
        z-index: 2;
        transform-origin: left top;
    }

    .floating-label input:focus + label,
    .floating-label input.has-value + label {
        top: -0.5rem;
        left: 0.5rem;
        font-size: 0.75rem;
        color: #007bff;
        font-weight: 500;
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

    .btn-save {
        background: #28a745;
        color: white;
    }

    .btn-save:hover {
        background: #218838;
        color: white;
        text-decoration: none;
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
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle floating labels for inputs with existing values
        const floatingInputs = document.querySelectorAll('.floating-label input');

        // Function to update label state
        function updateLabelState(input) {
            const hasValue = input.value && input.value.trim() !== '';
            if (hasValue) {
                input.classList.add('has-value');
            } else {
                input.classList.remove('has-value');
            }
        }

        floatingInputs.forEach(function(input) {
            // Force initial state check after a short delay to ensure DOM is ready
            setTimeout(function() {
                updateLabelState(input);
            }, 10);

            // Focus event
            input.addEventListener('focus', function() {
                this.classList.add('focused');
            });

            // Blur event
            input.addEventListener('blur', function() {
                this.classList.remove('focused');
                updateLabelState(this);
            });

            // Input event (as user types)
            input.addEventListener('input', function() {
                updateLabelState(this);
            });
        });

        // Also check after window load to catch any late-loading values
        window.addEventListener('load', function() {
            floatingInputs.forEach(function(input) {
                updateLabelState(input);
            });
        });
    });
</script>
@endsection

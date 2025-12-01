@extends('layouts.app')

@section('title', 'Landlord Database')

@section('content')
<h1 class="mb-4">Landlord Database</h1>

<div class="alert alert-info mb-4">
    <h4 class="alert-heading">New Legal Requirement</h4>
    <p class="mb-0">The Renters' Rights Act 2025 introduces a mandatory Private Rented Sector Database that all landlords must join.</p>
</div>

<h2 class="h4 mb-3">What is the PRS Database?</h2>

<p>The Renters' Rights Act 2025 introduces a new <strong>Private Rented Sector Database</strong> that all landlords of assured and regulated tenancies will be legally required to join. This creates a 'one stop shop' for landlords to access relevant guidance and helps them understand their obligations and demonstrate compliance.</p>

    <div class="row mt-4">
        <div class="col-md-4 mb-3">
            <div class="card h-100 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">For Landlords</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Single platform for all guidance</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Clear legal obligations</li>
                        <li class="mb-2"><i class="fas fa-check text-success me-2"></i>Demonstrate compliance easily</li>
                        <li class="mb-0"><i class="fas fa-check text-success me-2"></i>Stay informed of changes</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card h-100 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">For Tenants</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-clipboard-list text-primary me-2"></i>Increased transparency</li>
                        <li class="mb-2"><i class="fas fa-clipboard-list text-primary me-2"></i>Better property information</li>
                        <li class="mb-2"><i class="fas fa-clipboard-list text-primary me-2"></i>Know escalation routes</li>
                        <li class="mb-0"><i class="fas fa-clipboard-list text-primary me-2"></i>Support throughout tenancy</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card h-100 border-warning">
                <div class="card-header bg-warning text-dark">
                    <h5 class="card-title mb-0">For Local Councils</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2"><i class="fas fa-landmark text-warning me-2"></i>Identify problem properties</li>
                        <li class="mb-2"><i class="fas fa-landmark text-warning me-2"></i>Trusted intelligence source</li>
                        <li class="mb-2"><i class="fas fa-landmark text-warning me-2"></i>Reduce administration</li>
                        <li class="mb-0"><i class="fas fa-landmark text-warning me-2"></i>Focus on enforcement</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h4 mb-3 mt-4">Enforcement and Penalties</h2>

    <div class="alert alert-warning">
        <h5 class="alert-heading">Non-Compliance Penalties</h5>
        <div class="row">
            <div class="col-md-6">
                <strong>Initial Breaches:</strong><br>
                Civil penalties up to <span class="badge bg-warning text-dark">£7,000</span>
            </div>
            <div class="col-md-6">
                <strong>Repeated Breaches:</strong><br>
                Civil penalties up to <span class="badge bg-danger">£40,000</span> or criminal prosecution
            </div>
        </div>
        <hr>
        <p class="mb-0"><strong>Additional Consequence:</strong> Landlords who fail to register cannot obtain possession orders (except for serious tenant anti-social behaviour).</p>
    </div>

    <h2 class="h4 mb-3 mt-4">Current Status</h2>

<div class="card border-success mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">Implementation Timeline</h5>
    </div>
    <div class="card-body">
        <p><span class="badge bg-success">Royal Assent:</span> 27 October 2025 - Act is now law</p>
        <p><span class="badge bg-primary">Phase 1:</span> 1 May 2026 - Main provisions take effect</p>
        <p class="mb-0"><span class="badge bg-secondary">Database:</span> Expected to be operational in phases from 2026</p>
    </div>
</div>

    <h2 class="h4 mb-3">Why This Matters</h2>

<p>The database represents a <strong>significant step toward transparency and accountability</strong> in England's private rental sector, supporting both good landlords and protecting tenants' rights. It removes barriers that have historically made enforcement difficult while providing clear guidance for compliance.</p>
@endsection

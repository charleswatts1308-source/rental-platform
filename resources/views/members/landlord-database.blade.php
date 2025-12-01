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
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">For Landlords</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">Single platform for all guidance</li>
                        <li class="mb-2">Clear legal obligations</li>
                        <li class="mb-2">Demonstrate compliance easily</li>
                        <li class="mb-0">Stay informed of changes</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">For Tenants</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">Increased transparency</li>
                        <li class="mb-2">Better property information</li>
                        <li class="mb-2">Know escalation routes</li>
                        <li class="mb-0">Support throughout tenancy</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">For Local Councils</h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">Identify problem properties</li>
                        <li class="mb-2">Trusted intelligence source</li>
                        <li class="mb-2">Reduce administration</li>
                        <li class="mb-0">Focus on enforcement</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h4 mb-3 mt-4">Enforcement and Penalties</h2>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Non-Compliance Penalties</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <strong>Initial Breaches:</strong><br>
                    Civil penalties up to <strong>£7,000</strong>
                </div>
                <div class="col-md-6">
                    <strong>Repeated Breaches:</strong><br>
                    Civil penalties up to <strong>£40,000</strong> or criminal prosecution
                </div>
            </div>
            <hr>
            <p class="mb-0"><strong>Additional Consequence:</strong> Landlords who fail to register cannot obtain possession orders (except for serious tenant anti-social behaviour).</p>
        </div>
    </div>

    <h2 class="h4 mb-3 mt-4">Current Status</h2>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Implementation Timeline</h5>
    </div>
    <div class="card-body">
        <p><strong>Royal Assent:</strong> 27 October 2025 - Act is now law</p>
        <p><strong>Phase 1:</strong> 1 May 2026 - Main provisions take effect</p>
        <p class="mb-0"><strong>Database:</strong> Expected to be operational in phases from 2026</p>
    </div>
</div>

    <h2 class="h4 mb-3">Why This Matters</h2>

<p>The database represents a <strong>significant step toward transparency and accountability</strong> in England's private rental sector, supporting both good landlords and protecting tenants' rights. It removes barriers that have historically made enforcement difficult while providing clear guidance for compliance.</p>
@endsection

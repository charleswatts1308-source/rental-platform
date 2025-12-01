@extends('layouts.app')

@section('title', 'Landlord Database')

@section('content')
<h1 class="mb-4">Landlord Database</h1>

<div class="alert alert-info mb-4">
    <h4 class="alert-heading">New Legal Requirement</h4>
    <p class="mb-0">The Renters' Rights Act 2025 introduces a mandatory Private Rented Sector Database. All landlords must register.</p>
</div>

<!-- Planned Features -->
<section class="mb-5">
    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">Planned Features</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <ul class="small mb-md-0">
                        <li>Compulsory registration of all landlords</li>
                        <li>Property-level records for every rental</li>
                        <li>Compliance status visible to tenants</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="small mb-0">
                        <li>Local authority enforcement tools</li>
                        <li>Public search functionality</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Timeline -->
<section class="mb-5">
    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">Implementation Timeline</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <ul class="small mb-0">
                        <li><strong>Royal Assent:</strong> 27 October 2025</li>
                        <li><strong>Phase 1 (May 2026):</strong> Main provisions</li>
                        <li><strong>Phase 2 (Late 2026):</strong> Database rollout begins</li>
                        <li><strong>Full launch (2028):</strong> Nationwide coverage expected</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="alert alert-warning mb-0">
                        <p class="small mb-0"><strong>Note:</strong> Rollout is area-by-area. Public access follows registration in each region. Government IT timelines may slip.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why It's Needed -->
<section class="mb-5">
    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">Why It's Needed</h2>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <ul class="small mb-md-0">
                        <li>No central record of who lets properties</li>
                        <li>Rogue landlords operate undetected</li>
                        <li>Tenants can't verify landlord legitimacy</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <ul class="small mb-0">
                        <li>Local authorities lack PRS visibility</li>
                        <li>Compliance can't be monitored</li>
                        <li>Enforcement is reactive, not preventive</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Who Benefits -->
<section class="mb-5">
    <h2 class="h4 mb-3">Who Benefits</h2>
    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Landlords</h5></div>
                <div class="card-body">
                    <p class="small mb-0">Single platform for guidance and demonstrating compliance</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Tenants</h5></div>
                <div class="card-body">
                    <p class="small mb-0">Check landlord registration and verify property is legally let</p>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">Councils</h5></div>
                <div class="card-body">
                    <p class="small mb-0">Identify problem properties and target enforcement</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Penalties -->
<section class="mb-5">
    <div class="card">
        <div class="card-header">
            <h2 class="h5 mb-0">Non-Compliance Penalties</h2>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="h4 mb-1">£7,000</div>
                    <small class="text-muted">Initial breach</small>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="h4 mb-1">£40,000</div>
                    <small class="text-muted">Repeated breaches</small>
                </div>
                <div class="col-md-4">
                    <div class="h4 mb-1">No evictions</div>
                    <small class="text-muted">Until registered</small>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

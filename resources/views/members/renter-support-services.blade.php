@extends('layouts.app')

@section('title', 'Renter Support Services')

@section('content')
<h1 class="mb-4">Renters Support Services</h1>

<!-- Alert highlighting the gap -->
<div class="alert alert-info mb-4">
    <h4 class="alert-heading">The Service Gap Reality</h4>
        <p class="mb-0">While landlords enjoy dozens of comprehensive platforms and services, renters face a striking lack of dedicated support tools. This imbalance reflects the industry's focus on property owners rather than the people who actually live in rental properties.</p>
    </div>

    <!-- Comparison Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">The Stark Comparison</h2>

        <div class="row">
            <!-- Landlord Services -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">For Landlords: Abundant Choice</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col">
                                <div class="h2 mb-1">50+</div>
                                <small class="text-muted">Management Platforms</small>
                            </div>
                            <div class="col">
                                <div class="h2 mb-1">100+</div>
                                <small class="text-muted">Service Providers</small>
                            </div>
                        </div>

                        <p class="small mb-2"><strong>Categories available:</strong></p>
                        <ul class="small">
                            <li>All-in-one property management</li>
                            <li>Tenant referencing & screening</li>
                            <li>Rent collection & accounting</li>
                            <li>Maintenance management</li>
                            <li>Legal document generation</li>
                            <li>Insurance & protection products</li>
                            <li>Tax & compliance tools</li>
                            <li>Portfolio analytics</li>
                            <li>Professional associations (NRLA, etc.)</li>
                            <li>Educational resources & training</li>
                        </ul>

                        <p class="small mt-3 mb-0"><strong>Reality:</strong> Comprehensive ecosystem designed to maximize landlord success</p>
                    </div>
                </div>
            </div>

            <!-- Renter Services -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h5 mb-0">For Renters: Limited Options</h3>
                    </div>
                    <div class="card-body">
                        <div class="row text-center mb-3">
                            <div class="col">
                                <div class="h2 mb-1">5-10</div>
                                <small class="text-muted">Dedicated Services</small>
                            </div>
                            <div class="col">
                                <div class="h2 mb-1">Limited</div>
                                <small class="text-muted">Coverage</small>
                            </div>
                        </div>

                        <p class="small mb-2"><strong>What's typically missing:</strong></p>
                        <ul class="small">
                            <li>Rent payment tracking & credit building</li>
                            <li>Landlord performance ratings</li>
                            <li>Maintenance request management</li>
                            <li>Lease analysis & advice</li>
                            <li>Tenant rights advocacy</li>
                            <li>Move-in/move-out checklists</li>
                            <li>Deposit protection guidance</li>
                            <li>Rental market insights</li>
                            <li>Professional tenant associations</li>
                            <li>Educational resources</li>
                        </ul>

                        <p class="small mt-3 mb-0"><strong>Reality:</strong> Renters largely left to navigate the system alone</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- What Limited Services Exist -->
    <section class="mb-5">
        <h2 class="h3 mb-4">What Renter Services Do Exist</h2>

        <div class="alert alert-info mb-4">
            <p class="mb-0"><strong>Note:</strong> Most of these services are either very basic, have limited coverage, or are focused on the initial rental process rather than ongoing tenancy support.</p>
        </div>

        <div class="row">
            <!-- Credit & Financial -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h6 mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Credit & Financial Services
                        </h3>
                    </div>
                    <div class="card-body">
                        <ul class="small">
                            <li><strong>Canopy:</strong> Credit score tracking, rental payment reporting</li>
                            <li><strong>Credit Ladder:</strong> Rent payments reported to credit agencies</li>
                            <li><strong>Rental Exchange:</strong> Rent payment history for credit building</li>
                        </ul>
                        <div class="badge bg-secondary">Limited reach</div>
                    </div>
                </div>
            </div>

            <!-- Deposit Alternatives -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h6 mb-0">
                            <i class="fas fa-piggy-bank me-2"></i>
                            Deposit & Financial Barriers
                        </h3>
                    </div>
                    <div class="card-body">
                        <ul class="small">
                            <li><strong>flatfair:</strong> Deposit alternatives (mainly benefits landlords)</li>
                            <li><strong>Reposit:</strong> No-deposit options (limited availability)</li>
                            <li><strong>Zero Deposit:</strong> Insurance-based alternatives</li>
                        </ul>
                        <div class="badge bg-secondary">Primarily landlord-focused</div>
                    </div>
                </div>
            </div>

            <!-- Rights & Advice -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h6 mb-0">
                            <i class="fas fa-balance-scale me-2"></i>
                            Rights & Legal Support
                        </h3>
                    </div>
                    <div class="card-body">
                        <ul class="small">
                            <li><strong>Citizens Advice:</strong> General housing advice (overstretched)</li>
                            <li><strong>Shelter:</strong> Housing rights charity (limited capacity)</li>
                            <li><strong>Local Authority:</strong> Varies greatly by area</li>
                            <li><strong>ACORN:</strong> Community organizing (limited areas)</li>
                        </ul>
                        <div class="badge bg-secondary">Under-resourced</div>
                    </div>
                </div>
            </div>

            <!-- Property Search -->
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h6 mb-0">
                            <i class="fas fa-search me-2"></i>
                            Property Search (Not Tenancy Support)
                        </h3>
                    </div>
                    <div class="card-body">
                        <ul class="small">
                            <li><strong>Rightmove/Zoopla:</strong> Property search (advertising-driven)</li>
                            <li><strong>SpareRoom:</strong> Room sharing (limited protections)</li>
                            <li><strong>OpenRent:</strong> Direct from landlords (basic)</li>
                        </ul>
                        <div class="badge bg-secondary">Search only, not support</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- The Gap Analysis -->
    <section class="mb-5">
        <h2 class="h3 mb-4">What's Missing for Renters</h2>

        <div class="card">
            <div class="card-header">
                <h3 class="h5 mb-0">Critical Service Gaps</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h4 class="h6 mb-3">During Tenancy:</h4>
                        <ul class="small">
                            <li>Maintenance request tracking & escalation</li>
                            <li>Landlord performance monitoring</li>
                            <li>Rent payment history management</li>
                            <li>Utility setup & switching support</li>
                            <li>Tenancy documentation storage</li>
                            <li>Rights violation reporting</li>
                            <li>Neighbor/community connection</li>
                        </ul>
                    </div>
                    <div class="col-lg-6">
                        <h4 class="h6 mb-3">End of Tenancy:</h4>
                        <ul class="small">
                            <li>Deposit return assistance</li>
                            <li>Move-out checklist guidance</li>
                            <li>Dispute resolution support</li>
                            <li>Landlord reference tracking</li>
                            <li>Professional cleaning coordination</li>
                            <li>Damage assessment advice</li>
                            <li>Next tenancy preparation</li>
                        </ul>
                    </div>
                </div>

                <p class="small mt-4 mb-0"><strong>The Result:</strong> Renters face an information asymmetry where landlords have professional tools and support, while tenants navigate complex rental relationships with minimal assistance. This imbalance contributes to poor rental experiences and tenant exploitation.</p>
            </div>
        </div>
    </section>

    <!-- Our Solution -->
    <section class="mb-5">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Bridging the Gap: Our Renter-Focused Approach</h2>
            </div>
            <div class="card-body">
                <p class="mb-4">We recognize this service disparity and aim to provide renters with tools that level the playing field:</p>

                <div class="row">
                    <div class="col-md-6">
                        <h4 class="h6 mb-2">What We Offer:</h4>
                        <ul class="small">
                            <li>Renter database for collective strength</li>
                            <li>Landlord performance sharing</li>
                            <li>Group communication tools</li>
                            <li>Shared knowledge platform</li>
                            <li>Collective action coordination</li>
                            <li>Rights education resources</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h4 class="h6 mb-2">Our Philosophy:</h4>
                        <p class="small">If landlords have professional tools and support networks, renters deserve the same. By connecting tenants and sharing information, we help create a more balanced rental market.</p>

                        <div class="text-center mt-3">
                            <button type="button" class="btn btn-secondary">Join Our Renter Community</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="mb-5">
        <div class="text-center bg-light rounded p-4">
            <h2 class="h4 mb-3">Time to Change the Balance</h2>
            <p class="mb-4">The rental industry shouldn't just serve landlords. Renters deserve professional support, tools, and community too.</p>
            <div class="d-grid gap-2 d-md-block">
                <button type="button" class="btn btn-secondary btn-lg">Join the Movement</button>
                <button type="button" class="btn btn-outline-secondary btn-lg">Stay Updated</button>
            </div>
        </div>
    </section>
@endsection

@extends('layouts.app')

@section('title', 'Landlord Support Services')

@section('content')
<h1 class="mb-4">Landlord Support Services</h1>

<!-- Introduction -->
<div class="alert alert-info mb-4">
        <h4 class="alert-heading">Top UK Landlord Support Services & Platforms 2025</h4>
        <p class="mb-0">A comprehensive guide to the best property management platforms, services, and tools available to UK landlords.</p>
    </div>

    <!-- All-in-One Platforms Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">All-in-One Property Management Platforms</h2>

        <div class="row">
            <!-- Goodlord -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="h5 mb-0">Goodlord</h3>
                        <span class="badge bg-secondary">Featured</span>
                    </div>
                    <div class="card-body">
                        <p><strong>Focus:</strong> End-to-end lettings platform for agents and landlords</p>
                        <h6>Key Features:</h6>
                        <ul class="small">
                            <li>Tenant referencing</li>
                            <li>Insurance products</li>
                            <li>Rent collection</li>
                            <li>Tenancy management</li>
                            <li>Offer letters</li>
                            <li>Energy/broadband deals for tenants</li>
                        </ul>
                        <div class="mt-3">
                            <div class="badge bg-secondary mb-1">Revenue generation through tenant services</div>
                            <div class="badge bg-secondary mb-1">CPD-accredited training</div>
                        </div>
                        <p class="mt-2 mb-1"><strong>Best For:</strong> High tenant turnover, letting agents</p>
                        <p class="text-muted small mb-0"><strong>Note:</strong> Works primarily through letting agents, not directly with landlords</p>
                    </div>
                </div>
            </div>

            <!-- Landlord Vision -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0">Landlord Vision</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Focus:</strong> Comprehensive property management with accounting engine</p>
                        <h6>Key Features:</h6>
                        <ul class="small">
                            <li>Built-in accounting</li>
                            <li>Automated bank feeds</li>
                            <li>Receipt scanning</li>
                            <li>Legal document generation</li>
                            <li>Tenant management</li>
                        </ul>
                        <div class="badge bg-secondary mb-2">No need for additional accounting software like Xero</div>
                        <p class="mt-2 mb-1"><strong>Best For:</strong> Serious investors wanting all-in-one solution</p>
                        <p class="small mb-0"><strong>Pricing:</strong> 14-day free trial available</p>
                    </div>
                </div>
            </div>

            <!-- Landlord Studio -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0">Landlord Studio</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Focus:</strong> Property management and MTD-compliant accounting</p>
                        <h6>Key Features:</h6>
                        <ul class="small">
                            <li>iOS/Android apps</li>
                            <li>Receipt scanner</li>
                            <li>Mileage tracker</li>
                            <li>Bank feeds</li>
                            <li>18+ reports</li>
                            <li>Tenant management, rental listings</li>
                        </ul>
                        <div class="badge bg-secondary mb-2">MTD-ready for Making Tax Digital compliance</div>
                        <p class="mt-2 mb-1"><strong>Best For:</strong> Small to medium landlords</p>
                        <p class="small mb-0"><strong>Pricing:</strong> 30-day free trial, free version for up to 3 properties</p>
                    </div>
                </div>
            </div>

            <!-- Arthur Online -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0">Arthur Online</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Focus:</strong> Portfolio landlords and property managers</p>
                        <h6>Key Features:</h6>
                        <ul class="small">
                            <li>Cloud-based</li>
                            <li>Custom workflows</li>
                            <li>Integrated accounting (Xero)</li>
                            <li>Mobile access</li>
                            <li>Communication tools</li>
                        </ul>
                        <div class="badge bg-secondary mb-2">Highly scalable with custom processes</div>
                        <p class="mt-2 mb-0"><strong>Best For:</strong> Multiple units, bigger operations</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Specialist Services Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">Specialist Services</h2>

        <div class="row">
            <!-- PropertyJinni -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="h6 mb-0">PropertyJinni</h3>
                        <span class="badge bg-secondary">FREE</span>
                    </div>
                    <div class="card-body">
                        <p><strong>Focus:</strong> Free UK-optimized property management</p>
                        <ul class="small">
                            <li>Centralized database</li>
                            <li>Maintenance management</li>
                            <li>UK-specific templates</li>
                            <li>Goodlord/Van Mildert integrations</li>
                        </ul>
                        <p class="mb-1"><strong>Best For:</strong> Cost-conscious landlords, UK-specific compliance</p>
                    </div>
                </div>
            </div>

            <!-- Coho -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h6 mb-0">Coho</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Focus:</strong> HMO landlords and high turnover properties</p>
                        <p>Specialized for Houses in Multiple Occupation</p>
                        <p class="mb-1"><strong>Best For:</strong> HMO management, frequent tenant changes</p>
                        <p class="text-muted small mb-0"><strong>Note:</strong> 12-month minimum term</p>
                    </div>
                </div>
            </div>

            <!-- Landlordy -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h6 mb-0">Landlordy</h3>
                    </div>
                    <div class="card-body">
                        <p><strong>Focus:</strong> Mobile companion for private landlords</p>
                        <ul class="small">
                            <li>Rent tracking</li>
                            <li>Expense management</li>
                            <li>Safety regulation compliance</li>
                            <li>Document storage</li>
                            <li>Reminders</li>
                        </ul>
                        <p class="mb-0"><strong>Best For:</strong> Mobile-first landlords wanting simple tracking</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tenant Services Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">Tenant Services & Protection</h2>

        <div class="row">
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="h6 mb-0">Canopy</h4>
                    </div>
                    <div class="card-body">
                        <p class="small">Digital solutions for renters, agents, and landlords. Tenant screening, credit score tracking, rental payment reporting.</p>
                        <span class="badge bg-secondary">Comprehensive tenant vetting</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="h6 mb-0">flatfair</h4>
                    </div>
                    <div class="card-body">
                        <p class="small">Deposit alternatives and rental protection. Enhanced landlord protection, streamlined deposit processing.</p>
                        <span class="badge bg-secondary">Reducing tenant barriers</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header">
                        <h4 class="h6 mb-0">Reposit</h4>
                    </div>
                    <div class="card-body">
                        <p class="small">No-deposit renting solutions. Increased rent protection for landlords, reduced upfront costs for tenants.</p>
                        <span class="badge bg-secondary">Cash flow friendly</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Considerations Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">Key Considerations When Choosing</h2>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="h6 mb-0">Making Tax Digital (MTD) Compliance</h4>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Critical:</strong> Choose MTD-ready software if you earn £50k+ rental income</p>
                        <ul class="small">
                            <li><strong>Deadline:</strong> April 2026</li>
                            <li><strong>£30k threshold:</strong> 2027</li>
                            <li><strong>£20k threshold:</strong> 2028</li>
                        </ul>
                        <p class="small mb-0"><strong>MTD-Ready:</strong> Landlord Studio confirmed compliant</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="h6 mb-0">Portfolio Size Recommendations</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <strong>1-3 Properties:</strong><br>
                                <span class="small">Free versions (Landlord Studio, PropertyJinni)</span>
                            </li>
                            <li class="mb-2">
                                <strong>Medium Portfolio:</strong><br>
                                <span class="small">Paid tiers of comprehensive platforms</span>
                            </li>
                            <li class="mb-0">
                                <strong>Large Portfolio:</strong><br>
                                <span class="small">Arthur Online, enterprise solutions</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="h6 mb-0">Specific Needs</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li><strong>HMOs:</strong> Coho specialist solution</li>
                            <li><strong>High Turnover:</strong> Goodlord through agents</li>
                            <li><strong>Cost-Conscious:</strong> PropertyJinni (free)</li>
                            <li><strong>Mobile-First:</strong> Landlordy, Landlord Studio apps</li>
                            <li><strong>Accounting-Heavy:</strong> Landlord Vision</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h4 class="h6 mb-0">Pricing Models</h4>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled small">
                            <li><strong>Free Options:</strong> PropertyJinni, Landlord Studio (3 properties)</li>
                            <li><strong>Freemium:</strong> Most platforms offer limited free tiers</li>
                            <li><strong>Subscription:</strong> Monthly/annual fees scale with portfolio size</li>
                            <li><strong>Per-Property:</strong> Some charge per unit managed</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

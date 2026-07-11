@extends('layouts.app')

@section('title', 'Property Data Services')

@section('content')
<h1 class="mb-4">Property Data Services</h1>

<!-- Introduction -->
    <div class="alert alert-info mb-4">
        <h4 class="alert-heading">Property Data Services</h4>
        <p class="mb-0">Essential data sources for private rental sector (PRS) analysis, investment decisions, and market insights.</p>
    </div>

    <!-- Future Development Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">Future Government Database</h2>

        <div class="card">
            <div class="card-header">
                <h3 class="h6 mb-0">National PRS Database (Planned)</h3>
            </div>
            <div class="card-body">
                <p class="mb-3"><strong>Mandatory landlord registration under the Renters' Rights Act 2025</strong></p>
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="h6 mb-2">Planned Features:</h5>
                        <ul class="small">
                            <li>Compulsory registration of all landlords</li>
                            <li>Property-level records for every rental</li>
                            <li>Compliance status visible to tenants</li>
                            <li>Local authority enforcement tools</li>
                            <li>Public search functionality</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="h6 mb-2">Implementation Timeline:</h5>
                        <ul class="small">
                            <li><strong>Phase 2 (Late 2026):</strong> Database begins rolling out area-by-area</li>
                            <li><strong>Registration stage:</strong> Landlords required to register by region</li>
                            <li><strong>Full launch (2028):</strong> Expected mandatory membership nationwide</li>
                            <li><strong>Public access:</strong> Available after registration completes in each area</li>
                        </ul>
                    </div>
                </div>

                <div class="alert alert-warning mt-3 mb-0">
                    <p class="small mb-0"><strong>Reality check:</strong> Government IT projects frequently overrun. The database depends on building new infrastructure, coordinating with local authorities, and registering an estimated 2+ million landlords. While late 2026 is the target start date, full nationwide coverage and public access may take several years to achieve.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Insights Section -->
    <section class="mb-5">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Why Government Sees the Need for a PRS Database</h2>
            </div>
            <div class="card-body">
                <p class="mb-3">The government's decision to create a national PRS database stems from fundamental gaps in rental sector oversight:</p>
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="h6 mb-2">Tenant Protection:</h5>
                        <ul class="small">
                            <li><strong>No central record</strong> of who is letting properties</li>
                            <li><strong>Rogue landlords</strong> can operate undetected across areas</li>
                            <li><strong>Tenants cannot verify</strong> landlord legitimacy before signing</li>
                            <li><strong>Enforcement is reactive</strong> rather than preventive</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h5 class="h6 mb-2">Regulatory Oversight:</h5>
                        <ul class="small">
                            <li><strong>Local authorities lack visibility</strong> of PRS in their area</li>
                            <li><strong>Compliance cannot be monitored</strong> without knowing who landlords are</li>
                            <li><strong>Housing standards enforcement</strong> requires property identification</li>
                            <li><strong>Policy decisions</strong> need accurate sector data</li>
                        </ul>
                    </div>
                </div>
                <p class="small mt-3 mb-0"><strong>The Reality:</strong> Until now, millions of tenants have rented from landlords with no public accountability. The PRS database changes this - creating transparency that protects tenants and enables proper regulatory oversight of the rental sector.</p>
            </div>
        </div>
    </section>

    <!-- Government Sources Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">Government Sources</h2>

        <div class="card">
            <div class="card-header">
                <h3 class="h5 mb-0">ONS Private Rental Market Statistics</h3>
            </div>
            <div class="card-body">
                <p class="mb-3"><strong>Official benchmarks from the Office for National Statistics and English Private Landlord Survey</strong></p>

                <!-- Government Survey Card -->
                <div class="border-start border-primary border-3 ps-3 mb-4">
                    <h4 class="h6 mb-2">English Private Landlord Survey 2024</h4>
                    <p class="mb-2">The latest government survey focusing specifically on private landlords and their practices in England.</p>

                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="h6 mb-2">Regional Distribution of Landlords:</h5>
                            <ul class="list-unstyled small">
                                <li><strong>London:</strong> 27%</li>
                                <li><strong>South East:</strong> 17%</li>
                                <li><strong>South West:</strong> 11%</li>
                                <li><strong>East of England:</strong> 11%</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h5 class="h6 mb-2">Property Types in PRS:</h5>
                            <ul class="list-unstyled small">
                                <li><strong>Terraced properties:</strong> 34%</li>
                                <li><strong>Purpose-built flats:</strong> 29%</li>
                                <li><strong>Semi-detached houses:</strong> 16%</li>
                                <li><strong>Converted flats:</strong> 12%</li>
                                <li><strong>Detached houses:</strong> 5%</li>
                                <li><strong>Bungalows:</strong> 3%</li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-3">
                        <a href="https://www.gov.uk/government/statistics/english-private-landlord-survey-2024-main-report/english-private-landlord-survey-2024-main-report"
                           class="btn btn-outline-primary btn-sm" target="_blank">
                            View Full Report <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>

                <div class="alert alert-light">
                    <h5 class="h6 mb-2">Survey Coverage:</h5>
                    <ul class="mb-0 small">
                        <li><strong>English Private Landlord Survey:</strong> Focuses on landlord behavior and practices</li>
                        <li><strong>English Housing Survey:</strong> Broader coverage including tenant experiences across all sectors</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Commercial Leaders Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">Commercial Data Leaders</h2>

        <div class="row">
            <!-- HomeLet -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="h6 mb-0">HomeLet Rental Index</h3>
                        <span class="badge bg-secondary">Benchmark</span>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2"><strong>UK's most comprehensive rental benchmark</strong></p>
                        <ul class="small">
                            <li>Uses actual achieved rents (not advertised prices)</li>
                            <li>Designed with LSE methodology</li>
                            <li>Comprehensive market coverage</li>
                        </ul>
                        <div class="badge bg-secondary">Real transaction data</div>
                    </div>
                </div>
            </div>

            <!-- PropertyData -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h6 mb-0">PropertyData.co.uk</h3>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2"><strong>Real-time rental analytics</strong></p>
                        <ul class="small">
                            <li>Data from Rightmove, Zoopla, OnTheMarket</li>
                            <li>SpareRoom integration</li>
                            <li>Rental yield calculations</li>
                            <li>Market trend analysis</li>
                        </ul>
                        <div class="badge bg-secondary">Multi-platform data</div>
                    </div>
                </div>
            </div>

            <!-- Hometrack -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h6 mb-0">Hometrack</h3>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2"><strong>Rental estimates and valuations</strong></p>
                        <ul class="small">
                            <li>Updates every 30 days</li>
                            <li>1700% more new build comparables than Land Registry</li>
                            <li>Professional valuation tools</li>
                        </ul>
                        <div class="badge bg-secondary">Enhanced comparables</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Specialist Analytics Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4">Specialist PRS Analytics</h2>

        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h6 mb-0">NRLA Statistics</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>National Residential Landlords Association data</strong></p>
                        <ul class="small">
                            <li>Tracks 50+ PRS datasets</li>
                            <li>Sector-specific insights</li>
                            <li>Landlord behavior analysis</li>
                            <li>Market trend reporting</li>
                        </ul>
                        <div class="badge bg-secondary">Sector specialist</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="h6 mb-0">Rental Exchange</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Tenant payment behavior data</strong></p>
                        <ul class="small">
                            <li>Credit reporting integration</li>
                            <li>Payment history tracking</li>
                            <li>Tenant behavior patterns</li>
                            <li>Risk assessment data</li>
                        </ul>
                        <div class="badge bg-secondary">Payment analytics</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

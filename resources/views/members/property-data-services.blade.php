@extends('layouts.app')

@section('title', 'Property Data Services')

@section('content')
<h1 class="mb-4">Property Data Services</h1>

<!-- Introduction -->
    <div class="alert alert-info mb-4">
        <h4 class="alert-heading">Property Data Services</h4>
        <p class="mb-0">Essential data sources for private rental sector (PRS) analysis, investment decisions, and market insights.</p>
    </div>

    <!-- Government Sources Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4 text-primary">Government Sources</h2>

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
        <h2 class="h3 mb-4 text-primary">Commercial Data Leaders</h2>

        <div class="row">
            <!-- HomeLet -->
            <div class="col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="h6 mb-0">HomeLet Rental Index</h3>
                        <span class="badge bg-success">Benchmark</span>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2"><strong>UK's most comprehensive rental benchmark</strong></p>
                        <ul class="small">
                            <li>Uses actual achieved rents (not advertised prices)</li>
                            <li>Designed with LSE methodology</li>
                            <li>Comprehensive market coverage</li>
                        </ul>
                        <div class="badge bg-info">Real transaction data</div>
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
                        <div class="badge bg-warning text-dark">Multi-platform data</div>
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
                        <div class="badge bg-primary">Enhanced comparables</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Specialist Analytics Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4 text-primary">Specialist PRS Analytics</h2>

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
                        <div class="badge bg-success">Sector specialist</div>
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
                        <div class="badge bg-info">Payment analytics</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Future Development Section -->
    <section class="mb-5">
        <h2 class="h3 mb-4 text-primary">Future Government Database</h2>

        <div class="card border-warning">
            <div class="card-header bg-warning">
                <h3 class="h6 mb-0">PRS Database (Planned)</h3>
            </div>
            <div class="card-body">
                <p class="mb-3"><strong>Transparency portal under the Renters' Rights Act 2025</strong></p>
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="h6 mb-2">Planned Features:</h5>
                        <ul class="small">
                            <li>Landlord compliance tracking</li>
                            <li>Property history records</li>
                            <li>Regulatory oversight</li>
                            <li>Public transparency</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info py-2">
                            <small><strong>Status:</strong> In development as part of the Renters' Rights Act 2025 implementation</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Key Insights Section -->
    <section class="mb-5">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h2 class="h5 mb-0">Why PRS-Specific Data Matters</h2>
            </div>
            <div class="card-body">
                <p class="mb-3">PRS-specific data provides crucial insights that general property data cannot offer:</p>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="small">
                            <li><strong>Actual rental achievements</strong> rather than asking prices</li>
                            <li><strong>Tenant behavior patterns</strong> and payment history</li>
                            <li><strong>Regulatory compliance tracking</strong></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="small">
                            <li><strong>Yield calculations</strong> specific to rental properties</li>
                            <li><strong>Investment decision support</strong></li>
                            <li><strong>Management decision insights</strong></li>
                        </ul>
                    </div>
                </div>
                <div class="alert alert-success mt-3 mb-0">
                    <small><strong>Bottom Line:</strong> These data sources are critical for landlords making informed investment and management decisions in the private rental sector.</small>
                </div>
            </div>
        </div>
    </section>
@endsection

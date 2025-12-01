@extends('layouts.app')

@section('title', 'Renters Rights Act 2025')

@section('content')
<h1 class="mb-4">Renters' Rights Act 2025</h1>

<!-- Introduction -->
<div class="alert alert-info mb-4">
    <h4 class="alert-heading">Now Law - Royal Assent 27 October 2025</h4>
    <p class="mb-0">The Renters' Rights Act is now law. Here are the key changes as they affect renters, with implementation starting 1 May 2026.</p>
</div>

    <!-- Main Content -->
    <div class="row">
        <div class="col-lg-8">

            <!-- Change 1 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">1. Abolition of Section 21 "No-Fault" Evictions</h3>
                </div>
                <div class="card-body">
                    <p>The biggest change is ending Section 21 evictions, which currently allow landlords to evict tenants without providing any reason with just 2 months' notice. From 1 May 2026, landlords will no longer be able to end tenancies without providing a valid, legally defined reason, such as:</p>
                    <ul>
                        <li>Rent arrears</li>
                        <li>Anti-social behaviour</li>
                        <li>A desire to sell the property</li>
                    </ul>
                </div>
            </div>

            <!-- Change 2 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">2. End of Fixed-Term Tenancies - Move to Periodic Tenancies</h3>
                </div>
                <div class="card-body">
                    <p>The Renters' Rights Act will convert all fixed-term assured shorthold tenancies (ASTs) to periodic tenancies from 1 May 2026. Any fixed-term tenancies you sign now will be affected by this when the Act takes effect.</p>
                    <p>Both existing and new tenancies will be periodic, running from month to month without fixed terms. <strong>Tenants will be able to terminate at any time with two months' notice.</strong> This gives tenants much more flexibility to leave while providing ongoing security.</p>
                </div>
            </div>

            <!-- Change 3 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">3. Strict Rent Increase Restrictions</h3>
                </div>
                <div class="card-body">
                    <p>The shift to periodic tenancies means Section 13 notices will be the only way for landlords to raise the rent; these can only be served once per year.</p>
                    <p>The Act introduces stricter regulations on rent increases:</p>
                    <ul>
                        <li>Landlords will only be permitted to raise rents <strong>once a year</strong></li>
                        <li>Any increase must align with market rates</li>
                        <li>Tenants can challenge above-market rent increases through the First-tier Tribunal (Property Chamber)</li>
                    </ul>
                </div>
            </div>

            <!-- Change 4 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">4. Ban on Rental Bidding Wars and Rent-in-Advance Limits</h3>
                </div>
                <div class="card-body">
                    <p>The Act introduces important financial protections:</p>
                    <ul>
                        <li><strong>Ban on rental bidding wars</strong> - landlords and agents can't accept offers above the advertised price</li>
                        <li><strong>Rent payment limits</strong> - landlords won't be able to insist on tenants paying rent in advance, with rent payment/collection limited to one month in advance</li>
                    </ul>
                    <p>This prevents inflated rents in competitive markets and reduces financial barriers for tenants.</p>
                </div>
            </div>

            <!-- Change 5 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">5. New Property Standards and Anti-Discrimination Protections</h3>
                </div>
                <div class="card-body">
                    <p>The Act applies the <strong>Decent Homes Standard</strong> - all rental properties must meet minimum quality standards.</p>
                    <p>Key provisions include:</p>
                    <ul>
                        <li>Extension of <strong>Awaab's Law</strong> to the private rental sector, requiring landlords to address health hazards like mould</li>
                        <li>Prohibition of discrimination against those receiving benefits or with children</li>
                    </ul>
                </div>
            </div>

            <!-- Change 6 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">6. Right to Keep Pets</h3>
                </div>
                <div class="card-body">
                    <p>Tenants will have an <strong>implied right to have pets</strong>, and tenants will have the right to request to keep pets. Landlords must not unreasonably refuse such requests.</p>
                    <p>This marks a significant shift from the current system where many landlords blanket-ban pets from rental properties.</p>
                </div>
            </div>

            <!-- Change 7 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="h5 mb-0">7. Introduction of Landlord Redress Schemes and Private Rented Sector Database</h3>
                </div>
                <div class="card-body">
                    <p>The Act introduces significant regulatory infrastructure:</p>
                    <ul>
                        <li><strong>Landlord ombudsman</strong> - to help resolve disputes between landlords and tenants impartially</li>
                        <li><strong>Private rented sector database</strong> - designed to compile information about landlords and properties and provide visibility on compliance</li>
                        <li><strong>Requirement for landlords</strong> to sign up to a Landlord Redress Scheme as well as a PRS database</li>
                    </ul>
                    <p>This creates a formal framework for dispute resolution and regulatory oversight that doesn't currently exist in many areas, giving tenants better recourse when issues arise and helping authorities track landlord compliance across the sector.</p>
                </div>
            </div>

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="h6 mb-0">Other Significant Changes</h4>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li class="mb-2">
                            <strong>Enhanced Local Authority Powers:</strong> Local authorities will have enhanced powers to issue civil penalties on landlords
                        </li>
                        <li class="mb-2">
                            <strong>Written Tenancy Agreements:</strong> A written tenancy agreement will need to be provided to all tenants
                        </li>
                        <li class="mb-2">
                            <strong>Reformed Eviction Grounds:</strong> Expansion and clarification of Section 8 grounds for possession to replace Section 21
                        </li>
                    </ul>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h4 class="h6 mb-0">Timeline</h4>
                </div>
                <div class="card-body">
                    <p class="mb-1"><strong>Royal Assent:</strong> 27 October 2025 - Complete</p>
                    <p class="mb-1"><strong>Phase 1:</strong> 1 May 2026 - Main provisions take effect</p>
                    <p class="mb-0"><strong>Phases 2 & 3:</strong> Further provisions roll out in stages</p>
                </div>
            </div>
        </div>
    </div>
@endsection

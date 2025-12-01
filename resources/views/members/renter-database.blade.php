@extends('layouts.app')

@section('title', 'Renter Database')

@section('content')
<h1 class="mb-4">Renter Database</h1>

<div class="alert alert-info mb-4">
    <h4 class="alert-heading">Strength in Numbers</h4>
    <p class="mb-0">The private rented sector: 4.6 million households, £50+ billion per year, 3% of UK GDP. Group around your landlord and see what leverage you can develop.</p>
</div>

<!-- Two Databases -->
<section class="mb-5">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h2 class="h4 mb-4">Two Databases, Two Approaches</h2>

            <p class="mb-3">Renters.rent is a voluntary platform for tenants. No mandate, no enforcement, no massive budget. Just a simple idea: let renters build their own rental history and connect with others who share the same landlord.</p>

            <p class="mb-3"><strong>The government took a different route.</strong></p>

            <p class="mb-3">They're building a national Private Rented Sector Database. Every landlord in England will be required to register themselves and their properties. Let's see who gets there first.</p>
        </div>
    </div>
</section>

<!-- How It Works -->
<section class="mb-5">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h2 class="h4 mb-4">How the Renters Database Works</h2>

            <p class="mb-3">Register for an account and log in. Then add your rental profile - your property address, agent and landlord details.</p>

            <p class="mb-3">Once connected, this creates opportunities for:</p>

            <ul class="list-unstyled">
                <li class="mb-2"><strong>Shared Knowledge:</strong> Learn about other properties and service levels</li>
                <li class="mb-2"><strong>Experience Sharing:</strong> Compare your rental experience with others</li>
                <li class="mb-2"><strong>Collective Action:</strong> Work together to encourage landlord improvements</li>
                <li class="mb-0"><strong>Group Communication:</strong> Access to private group chat with fellow renters</li>
            </ul>
        </div>
    </div>
</section>

    <!-- Your Leverage -->
    <section class="mb-5">
        <h2 class="h4 mb-4">Understanding Your Leverage</h2>

        <div class="row">
            <!-- Multiple Properties -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0">Multiple Properties Landlord</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">When your landlord owns multiple properties, you have <strong>collective leverage</strong>:</p>
                        <ul class="small">
                            <li>Multiple tenants can coordinate concerns</li>
                            <li>Shared experiences reveal patterns</li>
                            <li>Group action has more impact</li>
                            <li>Landlord reputation across portfolio matters</li>
                            <li>Collective knowledge about standards and practices</li>
                        </ul>
                        <p class="small mt-3 mb-0"><strong>Advantage:</strong> Strength through unity with other tenants</p>
                    </div>
                </div>
            </div>

            <!-- Single Property -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="h5 mb-0">Single Property Landlord</h3>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">When you're the landlord's only tenant, you have <strong>unique importance</strong>:</p>
                        <ul class="small">
                            <li>You are their sole source of rental income</li>
                            <li>Your satisfaction directly affects their success</li>
                            <li>Property issues affect their only rental asset</li>
                            <li>Direct relationship without competition</li>
                            <li>Your feedback shapes their entire rental business</li>
                        </ul>
                        <p class="small mt-3 mb-0"><strong>Advantage:</strong> You are essential to their rental business success</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How to Join -->
    <section class="mb-5">
        <div class="card">
            <div class="card-header">
                <h2 class="h5 mb-0">Join the Renters Database</h2>
            </div>
            <div class="card-body">
                <p class="mb-4">Ready to connect with other renters and strengthen your position? Here's how to get started:</p>

                <div class="row">
                    <div class="col-md-8">
                        <ol class="mb-3">
                            <li class="mb-2"><strong>Sign up</strong> to our Renters Database</li>
                            <li class="mb-2"><strong>Provide your landlord details</strong> (we'll match you with others)</li>
                            <li class="mb-2"><strong>Get connected</strong> with fellow tenants of the same landlord</li>
                            <li class="mb-0"><strong>Access group chat</strong> and start sharing experiences</li>
                        </ol>

                        <div class="d-grid d-md-block">
                            <button type="button" class="btn btn-secondary btn-lg">Join Renters Database</button>
                            <button type="button" class="btn btn-outline-secondary btn-lg ms-md-2">Learn More</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3 text-center">
                            <h5 class="h6 mb-1">Safe & Secure</h5>
                            <small class="text-muted">Your privacy is protected</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benefits Summary -->
    <section class="mb-5">
        <div class="row">
            <div class="col-lg-8">
                <h2 class="h4 mb-3">Why Join?</h2>
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <div>
                            <h5 class="h6 mb-1">Shared Knowledge</h5>
                            <p class="small text-muted mb-0">Learn from other tenants' experiences</p>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div>
                            <h5 class="h6 mb-1">Collective Power</h5>
                            <p class="small text-muted mb-0">Stronger together than alone</p>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div>
                            <h5 class="h6 mb-1">Better Standards</h5>
                            <p class="small text-muted mb-0">Encourage landlord improvements</p>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div>
                            <h5 class="h6 mb-1">Direct Communication</h5>
                            <p class="small text-muted mb-0">Group chat with fellow tenants</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title">Ready to Get Started?</h5>
                        <p class="card-text small">Join thousands of renters already using collective action to improve their rental experience.</p>
                        <small class="text-muted">Stronger Together</small>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@extends('layouts.app')

@section('title', 'Renter Database')

@section('content')
<h1 class="mb-4">Renter Database</h1>

<div class="alert alert-info mb-4">
        <h4 class="alert-heading">Strength in Numbers</h4>
        <p class="mb-0">Connect with other renters who share the same landlord. Share knowledge, experiences, and work together for better rental conditions.</p>
    </div>

    <!-- Main Concept -->
    <section class="mb-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h2 class="h4 mb-4 text-primary">How the Renters Database Works</h2>
                <p class="lead text-muted mb-4">We help renters form groups around shared landlords to create collective strength and improve rental experiences.</p>

                <div class="row">
                    <div class="col-lg-8">
                        <p class="mb-3">When you join our Renters Database, you'll be connected with other tenants who rent from the same landlord. This creates opportunities for:</p>

                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Shared Knowledge:</strong> Learn about other properties and service levels
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Experience Sharing:</strong> Compare your rental experience with others
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Collective Action:</strong> Work together to encourage landlord improvements
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Group Communication:</strong> Access to private group chat with fellow renters
                            </li>
                        </ul>
                    </div>
                    <div class="col-lg-4">
                        <div class="text-center">
                            <div class="bg-light rounded p-3">
                                <i class="fas fa-comments fa-3x text-primary mb-2"></i>
                                <h5 class="h6 mb-0">Group Chat Access</h5>
                                <small class="text-muted">Connect with other tenants</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Your Leverage -->
    <section class="mb-5">
        <h2 class="h4 mb-4 text-primary">Understanding Your Leverage</h2>

        <div class="row">
            <!-- Multiple Properties -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100 border-success">
                    <div class="card-header bg-success text-white">
                        <h3 class="h5 mb-0">
                            <i class="fas fa-building me-2"></i>
                            Multiple Properties Landlord
                        </h3>
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
                        <div class="alert alert-success mt-3 mb-0">
                            <small><strong>Advantage:</strong> Strength through unity with other tenants</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Single Property -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100 border-info">
                    <div class="card-header bg-info text-white">
                        <h3 class="h5 mb-0">
                            <i class="fas fa-home me-2"></i>
                            Single Property Landlord
                        </h3>
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
                        <div class="alert alert-info mt-3 mb-0">
                            <small><strong>Advantage:</strong> You are essential to their rental business success</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How to Join -->
    <section class="mb-5">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h2 class="h5 mb-0">
                    <i class="fas fa-user-plus me-2"></i>
                    Join the Renters Database
                </h2>
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
                            <button type="button" class="btn btn-primary btn-lg">
                                <i class="fas fa-users me-2"></i>
                                Join Renters Database
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-lg ms-md-2">
                                <i class="fas fa-info-circle me-2"></i>
                                Learn More
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-light rounded p-3 text-center">
                            <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
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
                <h2 class="h4 mb-3 text-primary">Why Join?</h2>
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-lightbulb text-warning fa-lg me-3"></i>
                            </div>
                            <div>
                                <h5 class="h6 mb-1">Shared Knowledge</h5>
                                <p class="small text-muted mb-0">Learn from other tenants' experiences</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-handshake text-success fa-lg me-3"></i>
                            </div>
                            <div>
                                <h5 class="h6 mb-1">Collective Power</h5>
                                <p class="small text-muted mb-0">Stronger together than alone</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-chart-line text-primary fa-lg me-3"></i>
                            </div>
                            <div>
                                <h5 class="h6 mb-1">Better Standards</h5>
                                <p class="small text-muted mb-0">Encourage landlord improvements</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-comments text-info fa-lg me-3"></i>
                            </div>
                            <div>
                                <h5 class="h6 mb-1">Direct Communication</h5>
                                <p class="small text-muted mb-0">Group chat with fellow tenants</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card bg-light">
                    <div class="card-body text-center">
                        <h5 class="card-title">Ready to Get Started?</h5>
                        <p class="card-text small">Join thousands of renters already using collective action to improve their rental experience.</p>
                        <div class="text-center">
                            <div class="h2 text-primary mb-1">
                                <i class="fas fa-users"></i>
                            </div>
                            <small class="text-muted">Stronger Together</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
